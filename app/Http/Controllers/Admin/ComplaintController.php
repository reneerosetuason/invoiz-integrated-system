<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    public function index(Request $request)
    {
        $query = Complaint::with(['buyer', 'seller', 'order']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%")
                  ->orWhere('status', 'like', "%{$search}%")
                  ->orWhereHas('buyer', function ($b) use ($search) {
                      $b->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('status') && in_array($request->status, ['open','in_review','resolved','closed'])) {
            $query->where('status', $request->status);
        }

        $complaints = $query->orderBy('created_at', 'desc')->get();
        $stats = [
            'all' => Complaint::count(),
            'open' => Complaint::where('status','open')->count(),
            'in_review' => Complaint::where('status','in_review')->count(),
            'resolved' => Complaint::where('status','resolved')->count(),
        ];
        return view('admin.complaints', compact('complaints','stats'));
    }

    public function show(Complaint $complaint)
    {
        $complaint->load(['buyer', 'seller.user', 'order']);
        return view('admin.complaint-detail', compact('complaint'));
    }

    public function resolve(Request $request, Complaint $complaint)
    {
        $request->validate([
            'resolution' => 'required|string',
            'status' => 'required|in:resolved,closed',
        ]);

        $complaint->update([
            'resolution' => $request->resolution,
            'status' => $request->status,
            'resolved_at' => now(),
        ]);

        return redirect()->back()->with('status', 'Complaint resolved successfully.');
    }
}

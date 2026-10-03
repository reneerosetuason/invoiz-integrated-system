<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\NotificationsLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SellerController extends Controller
{
    public function index(Request $request)
    {
        $query = Seller::with(['user', 'products']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('store_name', 'like', "%{$search}%")
                  ->orWhere('business_name', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($q2) use ($search) {
                      $q2->where('name', 'like', "%{$search}%")
                         ->orWhere('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Status filter mapped to shared invoizdb schema:
        // pending/rejected -> approval_status, suspended -> status=inactive,
        // approved -> approval_status=approved (+ legacy status=approved).
        if ($request->filled('status') && in_array($request->status, ['pending', 'approved', 'suspended', 'rejected'])) {
            match ($request->status) {
                'pending' => $query->where('approval_status', 'pending'),
                'rejected' => $query->where('approval_status', 'rejected'),
                'suspended' => $query->where('status', 'inactive'),
                'approved' => $query->approved(),
            };
        }

        $sellers = $query->withCount('products')->orderBy('created_at', 'desc')->get();
        $stats = [
            'all' => Seller::count(),
            'pending' => Seller::where('approval_status', 'pending')->count(),
            'approved' => Seller::approved()->count(),
            'suspended' => Seller::where('status', 'inactive')->count(),
            'rejected' => Seller::where('approval_status', 'rejected')->count(),
        ];
        if ($request->wantsJson()) {
            return $sellers;
        }
        return view('admin.sellers', compact('sellers', 'stats'));
    }

    public function approve(Request $request, Seller $seller)
    {
        // Write both schemas so buyer/seller apps see the approval too.
        $seller->approval_status = 'approved';
        $seller->status = 'active';
        $seller->save();

        NotificationsLog::create([
            'user_id' => $seller->user_id,
            'type' => 'registration_approved',
            'subject' => 'Your Seller Registration Has Been Approved',
            'body' => "Dear {$seller->user->name},\n\nCongratulations! Your seller account for \"{$seller->store_name}\" has been approved. You can now start listing products on our platform.",
            'email' => $seller->user->email,
            'sent' => true,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Seller approved', 'seller' => $seller]);
        }
        return redirect()->back()->with('status', "Seller \"{$seller->store_name}\" has been approved.");
    }

    public function reject(Request $request, Seller $seller)
    {
        $request->validate(['reason' => 'nullable|string']);

        $seller->approval_status = 'rejected';
        $seller->status = 'inactive';
        $seller->save();

        NotificationsLog::create([
            'user_id' => $seller->user_id,
            'type' => 'registration_rejected',
            'subject' => 'Your Seller Registration Update',
            'body' => "Dear {$seller->user->name},\n\nWe regret to inform you that your seller registration for \"{$seller->store_name}\" was not approved." . ($request->reason ? "\n\nReason: {$request->reason}" : ''),
            'email' => $seller->user->email,
            'sent' => true,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Seller rejected', 'seller' => $seller]);
        }
        return redirect()->back()->with('status', "Seller \"{$seller->store_name}\" has been rejected.");
    }

    public function suspend(Request $request, Seller $seller)
    {
        // Shared schema has no 'suspended' for sellers.status -> use inactive.
        $seller->status = 'inactive';
        $seller->save();

        if ($seller->user) {
            $seller->user->account_status = 'suspended';
            $seller->user->status = 'suspended';
            $seller->user->save();
        }

        NotificationsLog::create([
            'user_id' => $seller->user_id,
            'type' => 'account_suspended',
            'subject' => 'Your Seller Account Has Been Suspended',
            'body' => "Dear {$seller->user->name},\n\nYour seller account \"{$seller->store_name}\" has been suspended due to a compliance violation. Please contact support for assistance.",
            'email' => $seller->user->email,
            'sent' => true,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Seller suspended', 'seller' => $seller]);
        }
        return redirect()->back()->with('status', "Seller \"{$seller->store_name}\" has been suspended.");
    }

    public function reinstate(Request $request, Seller $seller)
    {
        $seller->approval_status = 'approved';
        $seller->status = 'active';
        $seller->save();

        if ($seller->user) {
            $seller->user->account_status = 'active';
            $seller->user->status = 'active';
            $seller->user->save();
        }

        NotificationsLog::create([
            'user_id' => $seller->user_id,
            'type' => 'account_reinstated',
            'subject' => 'Your Seller Account Has Been Reinstated',
            'body' => "Dear {$seller->user->name},\n\nGood news! Your seller account \"{$seller->store_name}\" has been reinstated and is now active again.",
            'email' => $seller->user->email,
            'sent' => true,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Seller reinstated', 'seller' => $seller]);
        }
        return redirect()->back()->with('status', "Seller \"{$seller->store_name}\" has been reinstated and is active again.");
    }

    public function show(Request $request, Seller $seller)
    {
        $seller->load('user', 'profile', 'products');
        if ($request->wantsJson()) {
            return $seller;
        }
        return view('admin.seller-detail', compact('seller'));
    }


}

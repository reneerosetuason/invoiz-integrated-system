<?php

namespace App\Http\Controllers\Logistics;

use App\Models\Delivery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrackingController extends Controller
{
    public function index(): View
    {
        return view('logistics.tracking.index');
    }

    public function search(Request $request): View
    {
        $validated = $request->validate([
            'tracking_number' => 'required|string',
        ]);

        $delivery = Delivery::with([
            'statusLogs.changer',
            'proofs.rider',
            'rider',
            'creator',
        ])
        ->where('tracking_number', trim($validated['tracking_number']))
        ->first();

        return view('logistics.tracking.show', [
            'delivery' => $delivery,
            'transitions' => config('logistics.transitions'),
        ]);
    }
}





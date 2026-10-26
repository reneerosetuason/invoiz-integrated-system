<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\WithdrawalRequest;
use App\Support\Payments;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    public function index()
    {
        $userId = auth()->id();
        $seller = Seller::where('user_id', $userId)->firstOrFail();

        $available = Payments::availableBalanceForUser($userId);

        $requests = WithdrawalRequest::where('seller_id', $seller->id)
            ->latest()
            ->paginate(10);

        $hasPending = WithdrawalRequest::where('seller_id', $seller->id)
            ->where('status', 'pending')
            ->exists();

        $minimum = max(100, (float) Setting::get('payout_minimum', 100));

        return view('seller.payouts.index', compact('seller', 'available', 'requests', 'hasPending', 'minimum'));
    }

    public function request(Request $request)
    {
        $userId = auth()->id();
        $seller = Seller::where('user_id', $userId)->firstOrFail();

        $minimum = max(100, (float) Setting::get('payout_minimum', 100));

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:' . $minimum, 'max:1000000'],
            'method' => ['required', 'in:Bank Transfer,GCash,PayMaya'],
        ]);

        if (WithdrawalRequest::where('seller_id', $seller->id)->where('status', 'pending')->exists()) {
            return back()->withErrors(['amount' => 'You already have a pending payout request. Wait for it to be processed.']);
        }

        $available = Payments::availableBalanceForUser($userId);

        if ((float) $data['amount'] > $available) {
            return back()->withErrors([
                'amount' => 'You can only request up to your available balance of ₱' . number_format($available, 2) . '.',
            ]);
        }

        WithdrawalRequest::create([
            'seller_id' => $seller->id,
            'amount'    => round((float) $data['amount'], 2),
            'status'    => 'pending',
            'method'    => $data['method'],
        ]);

        return back()->with('success', 'Payout request submitted. Our team reviews requests on the configured schedule.');
    }
}

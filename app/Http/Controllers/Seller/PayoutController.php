<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\WithdrawalRequest;
use App\Support\Payments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            'amount' => ['required', 'numeric', 'min:' . $minimum, 'max:1000000', 'decimal:0,2'],
            'method' => ['required', 'in:Bank Transfer,GCash,PayMaya'],
        ]);

        DB::transaction(function () use ($seller, $userId, $data): void {
            Seller::query()->whereKey($seller->id)->lockForUpdate()->firstOrFail();
            if (WithdrawalRequest::where('seller_id', $seller->id)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['amount' => 'You already have a pending payout request.']);
            }
            $available = Payments::availableBalanceForUser($userId);
            if (round((float) $data['amount'], 2) > $available) {
                throw ValidationException::withMessages(['amount' => 'The amount exceeds your available balance.']);
            }
            WithdrawalRequest::create([
                'seller_id' => $seller->id,
                'amount' => round((float) $data['amount'], 2),
                'status' => 'pending',
                'method' => $data['method'],
            ]);
        });

        return back()->with('success', 'Payout request submitted. Our team reviews requests on the configured schedule.');
    }
}

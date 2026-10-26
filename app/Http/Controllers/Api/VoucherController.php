<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Models\VoucherClaim;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        $vouchers = Voucher::active()
            ->where(fn ($q) => $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))
            ->orderBy('min_spend')->get();
        $claimed = $request->user()
            ? VoucherClaim::where('buyer_id', $request->user()->id)->pluck('voucher_id')->map(fn ($id) => (int) $id)->all()
            : [];
        $vouchers->each(function ($voucher) use ($claimed) {
            $voucher->setAttribute('is_claimed', in_array((int) $voucher->id, $claimed, true));
            $this->hideBuyerCode($voucher);
        });

        return response()->json(['vouchers' => $vouchers]);
    }

    public function claimed(Request $request)
    {
        $claims = VoucherClaim::with('voucher')
            ->where('buyer_id', $request->user()->id)->whereNull('used_at')
            ->whereHas('voucher', fn ($q) => $q->active()->where(fn ($x) => $x->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit')))
            ->latest('claimed_at')->get();
        foreach ($claims as $claim) {
            if ($claim->voucher) $this->hideBuyerCode($claim->voucher);
        }

        return response()->json(['vouchers' => $claims]);
    }

    public function claim(Request $request, Voucher $voucher)
    {
        if (! $voucher->is_active || ($voucher->usage_limit !== null && $voucher->used_count >= $voucher->usage_limit)) {
            return response()->json(['message' => 'This voucher is no longer available.'], 422);
        }

        $existing = VoucherClaim::where([
            'buyer_id' => $request->user()->id,
            'voucher_id' => $voucher->id,
        ])->first();
        if ($existing?->used_at) {
            return response()->json(['message' => 'You already used this voucher.'], 409);
        }
        $claim = $existing ?: VoucherClaim::create([
            'buyer_id' => $request->user()->id,
            'voucher_id' => $voucher->id,
            'claimed_at' => now(),
        ]);
        $claim->load('voucher');
        if ($claim->voucher) $this->hideBuyerCode($claim->voucher);

        return response()->json(['message' => 'Voucher claimed.', 'claim' => $claim], 201);
    }

    public function validateCode(Request $request)
    {
        $validated = $request->validate([
            'code'      => ['required', 'string', 'max:12'],
            'subtotal'  => ['required', 'numeric', 'min:0'],
        ]);

        $voucher = Voucher::active()->where('code', strtoupper(trim($validated['code'])))->first();

        if (! $voucher) {
            return response()->json(['message' => 'Invalid or expired voucher code.'], 422);
        }

        if ($validated['subtotal'] < $voucher->min_spend) {
            return response()->json([
                'message' => "This voucher requires a minimum spend of ₱{$voucher->min_spend}.",
            ], 422);
        }

        $type = $voucher->discount_type ?? $voucher->type;
        if ($type === 'free_shipping') {
            $cap = (float) ($voucher->discount_value ?? 0) > 0
                ? (float) $voucher->discount_value
                : \App\Models\Order::SHIPPING_FEE_PER_SELLER;
            if ($voucher->max_discount !== null) {
                $cap = min($cap, (float) $voucher->max_discount);
            }

            return response()->json([
                'message'             => 'Voucher is valid.',
                'voucher'             => $voucher,
                'discount'            => 0,
                'free_shipping_cover' => round($cap, 2),
            ]);
        }

        $discount = $voucher->discount_type === 'fixed'
            ? $voucher->discount_value
            : round($validated['subtotal'] * $voucher->discount_value / 100, 2);

        if ($voucher->discount_type === 'percent' && $voucher->max_discount && $discount > $voucher->max_discount) {
            $discount = $voucher->max_discount;
        }

        if ($discount > $validated['subtotal']) {
            $discount = $validated['subtotal'];
        }

        return response()->json([
            'message'  => 'Voucher is valid.',
            'voucher'  => $voucher,
            'discount' => round($discount, 2),
        ]);
    }

    private function hideBuyerCode(Voucher $voucher): void
    {
        if ($voucher->name === $voucher->code) {
            $voucher->setAttribute('name', 'Store offer');
        }
        $voucher->makeHidden('code');
    }
}

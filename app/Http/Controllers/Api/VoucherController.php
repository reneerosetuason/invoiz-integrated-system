<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    public function index()
    {
        $vouchers = Voucher::active()->orderBy('min_spend')->get();

        return response()->json(['vouchers' => $vouchers]);
    }

    public function validateCode(Request $request)
    {
        $validated = $request->validate([
            'code'      => ['required', 'string', 'max:50'],
            'subtotal'  => ['required', 'numeric', 'min:0'],
        ]);

        $voucher = Voucher::active()->where('code', $validated['code'])->first();

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
}
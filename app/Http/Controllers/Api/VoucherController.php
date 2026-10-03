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
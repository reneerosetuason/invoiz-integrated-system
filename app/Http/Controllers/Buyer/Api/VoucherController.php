<?php

namespace App\Http\Controllers\Buyer\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Voucher;
use App\Models\VoucherClaim;
use App\Support\Shipping;
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
            // Kept for old clients; prices and store ownership come from the cart.
            'subtotal'  => ['nullable', 'numeric', 'min:0'],
            'cart_item_ids' => ['sometimes', 'array', 'min:1', 'max:200'],
            'cart_item_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
        ]);

        $voucher = Voucher::active()->where('code', strtoupper(trim($validated['code'])))->first();

        if (! $voucher) {
            return response()->json(['message' => 'Invalid or expired voucher code.'], 422);
        }

        $selected = $validated['cart_item_ids'] ?? null;
        $lines = $selected === null
            ? Cart::linesFor($request->user()->id)
            : Cart::selectedLinesFor($request->user()->id, $selected);
        if ($lines->isEmpty() || ($selected !== null && $lines->count() !== count($selected))
            || $lines->contains(fn ($line) => $line['qty'] < 1 || Cart::availableFor($line['product'], $line['variant'], $line['item']->variant_id) < $line['qty'])) {
            return response()->json(['message' => 'Your selected cart items are empty or unavailable.'], 422);
        }
        $sellerSubtotals = $lines->groupBy(fn ($line) => (int) $line['product']->seller_id)
            ->map(fn ($group) => (float) $group->sum('line'))->all();
        $resolved = Shipping::resolveVoucher($voucher, array_sum($sellerSubtotals), array_keys($sellerSubtotals), $sellerSubtotals);
        if ($resolved['error']) return response()->json(['message' => $resolved['error']], 422);
        $this->hideBuyerCode($voucher);

        return response()->json([
            'message'  => 'Voucher is valid.',
            'voucher'  => $voucher,
            'discount' => $resolved['merch'],
            'free_shipping_cover' => round(array_sum($resolved['waived']), 2),
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

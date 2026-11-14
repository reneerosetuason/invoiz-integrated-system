<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;

use App\Models\Favorite;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\Voucher;
use App\Models\VoucherClaim;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BuyerFeaturesController extends Controller
{
    private function buyerId(Request $request): ?int
    {
        return (int) ($request->session()->get('buyer.id') ?? (Auth::check() ? Auth::id() : 0)) ?: null;
    }

    public function wishlist(Request $request)
    {
        $buyerId = $this->buyerId($request);
        if (! $buyerId) return redirect('/login')->with('error', 'Please login to view your wishlist.');

        $favorites = Favorite::with(['product' => fn ($query) => $query->withTrashed()->with('category')])
            ->where('buyer_id', $buyerId)
            ->latest('created_at')->paginate(12);

        return view('buyer.wishlist', compact('favorites'));
    }

    public function toggleFavorite(Request $request, int $productId)
    {
        $buyerId = $this->buyerId($request);
        if (! $buyerId) return redirect('/login')->with('error', 'Please login to save products.');
        $product = Product::withTrashed()->findOrFail($productId);

        $favorite = Favorite::where('buyer_id', $buyerId)->where('product_id', $product->id)->first();
        if ($favorite) {
            $favorite->delete();
            $message = 'Removed from your wishlist.';
        } else {
            if ($product->trashed() || $product->status !== 'active') return back()->with('error', 'This product is no longer available.');
            Favorite::firstOrCreate(['buyer_id' => $buyerId, 'product_id' => $product->id]);
            $message = 'Added to your wishlist.';
        }

        return back()->with('success', $message);
    }

    public function vouchers(Request $request)
    {
        $buyerId = $this->buyerId($request);
        if (! $buyerId) return redirect('/login')->with('error', 'Please login to claim vouchers.');

        $voucherClaims = VoucherClaim::where('buyer_id', $buyerId)->get()->keyBy('voucher_id');
        $claimedIds = $voucherClaims->keys()->map(fn ($id) => (int) $id)->all();

        $vouchers = Voucher::active()
            ->where(function ($q) {
                $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit');
            })
            ->latest()->paginate(12);

        // Discovery does not require a cart. Checkout still validates store,
        // minimum spend, expiry and usage against the selected items.
        return view('buyer.vouchers', compact('vouchers', 'claimedIds', 'voucherClaims'));
    }

    public function claimVoucher(Request $request, Voucher $voucher)
    {
        $buyerId = $this->buyerId($request);
        if (! $buyerId) return redirect('/login')->with('error', 'Please login to claim vouchers.');
        if (! $voucher->is_active || ($voucher->usage_limit !== null && $voucher->used_count >= $voucher->usage_limit)) {
            return back()->with('error', 'This voucher is no longer available.');
        }

        $claim = VoucherClaim::where('buyer_id', $buyerId)->where('voucher_id', $voucher->id)->first();
        if ($claim?->used_at) return back()->with('error', 'You already used this voucher.');
        if (! $claim) VoucherClaim::create(['buyer_id' => $buyerId, 'voucher_id' => $voucher->id, 'claimed_at' => now()]);
        return back()->with('success', 'Voucher claimed. You can select it at checkout.');
    }

    public function rate(Request $request, int $orderId, int $productId)
    {
        $buyerId = $this->buyerId($request);
        if (! $buyerId) return redirect('/login')->with('error', 'Please login to rate a product.');

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        $order = Order::where('buyer_id', $buyerId)->with(['items', 'deliveries'])->findOrFail($orderId);
        $isDelivered = $order->status === 'delivered'
            || $order->deliveries->contains('status', 'delivered')
            || \App\Support\CustomerTracking::progressStage($order, $order->deliveries->first()) === 'delivered';

        if (! $isDelivered) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => 'You can rate products after the order is delivered.'], 422);
            }
            return back()->with('error', 'You can rate products after the order is delivered.');
        }

        if ($order->status !== 'delivered') {
            $order->update(['status' => 'delivered']);
        }

        if (! $order->items->contains('product_id', $productId)) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => 'That product is not part of this order.'], 422);
            }
            return back()->with('error', 'That product is not part of this order.');
        }

        DB::transaction(function () use ($buyerId, $order, $productId, $data) {
            Review::updateOrCreate(
                ['buyer_id' => $buyerId, 'product_id' => $productId, 'order_id' => $order->id],
                ['rating' => $data['rating'], 'comment' => $data['comment'] ?? null, 'status' => 'visible']
            );
            $average = Review::where('product_id', $productId)->where('status', 'visible')->avg('rating');
            Product::whereKey($productId)->update(['rating' => $average === null ? null : round((float) $average, 1)]);
        });

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Thank you for sharing your rating!',
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? '',
            ]);
        }

        return back()->with('success', 'Thanks for sharing your rating.');
    }
}

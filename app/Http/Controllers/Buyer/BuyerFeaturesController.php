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

        // Determine which sellers the buyer has in their cart so we only
        // surface vouchers they can actually apply at checkout.
        $cartSellerIds = \App\Models\Cart::linesFor($buyerId)
            ->map(fn ($line) => (int) $line['product']->seller_id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        // Cart items reference users.id; vouchers reference sellers.id.
        // recordIds() includes safe legacy keys without crossing store ownership.
        $voucherSellerIds = \App\Models\Seller::whereIn('user_id', $cartSellerIds)->get()
            ->flatMap(fn ($seller) => $seller->recordIds())->unique()->values()->all();

        $voucherClaims = VoucherClaim::where('buyer_id', $buyerId)->get()->keyBy('voucher_id');
        $claimedIds = $voucherClaims->keys()->map(fn ($id) => (int) $id)->all();

        $vouchers = Voucher::active()
            ->where(function ($q) {
                $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit');
            })
            ->where(function ($q) use ($voucherSellerIds) {
                // Platform-wide vouchers (no seller) or vouchers from a seller in the buyer's cart.
                $q->whereNull('seller_id')
                  ->orWhereIn('seller_id', $voucherSellerIds);
            })
            ->latest()->paginate(12);

        return view('buyer.vouchers', compact('vouchers', 'claimedIds', 'voucherClaims', 'cartSellerIds'));
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

        $order = Order::where('buyer_id', $buyerId)->with('items')->findOrFail($orderId);
        if ($order->status !== 'delivered') {
            return back()->with('error', 'You can rate products after the order is delivered.');
        }
        if (! $order->items->contains('product_id', $productId)) {
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

        return back()->with('success', 'Thanks for sharing your rating.');
    }
}

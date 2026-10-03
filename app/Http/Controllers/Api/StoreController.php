<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\Seller;
use App\Models\StoreFollow;
use App\Models\User;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    /**
     * Public storefront for a seller: shop info, stats and their products.
     * {seller} is the seller's user id (products.seller_id).
     */
    public function show(Request $request, $sellerUserId)
    {
        $seller = Seller::where('user_id', $sellerUserId)->first();
        $user = User::find($sellerUserId);

        if (! $seller || ! $user) {
            return response()->json(['message' => 'Store not found.'], 404);
        }

        $products = Product::query()
            ->with(['category', 'variants'])
            ->where('seller_id', $sellerUserId)
            ->where('status', 'active');

        // In-store search (Shopee-style search bar on the store page).
        if ($request->has('search') && $request->filled('search')) {
            $products->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->input('search').'%')
                    ->orWhere('description', 'like', '%'.$request->input('search').'%');
            });
        }

        // In-store sort.
        if ($request->has('sort')) {
            switch ($request->input('sort')) {
                case 'price_asc':
                    $products->orderBy('price', 'asc');
                    break;
                case 'price_desc':
                    $products->orderBy('price', 'desc');
                    break;
                case 'rating':
                    $products->orderByDesc('rating');
                    break;
                default:
                    $products->latest();
            }
        } else {
            $products->latest();
        }

        $products = $products->paginate($request->integer('per_page', 12));

        $counts = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('order_items.product_id', $products->pluck('id')->all())
            ->where('orders.status', '!=', 'cancelled')
            ->selectRaw('order_items.product_id, SUM(order_items.quantity) as total')
            ->groupBy('order_items.product_id')
            ->pluck('total', 'product_id')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        $products->getCollection()->each(function ($product) use ($counts) {
            $product->sold = $counts[$product->id] ?? 0;
        });

        $store = $this->storeInfo($request, $sellerUserId, $products->total());

        return response()->json([
            'store'    => $store,
            'products' => $products,
        ]);
    }

    /**
     * Reviews across the store's products, newest first.
     */
    public function reviews(Request $request, $sellerUserId)
    {
        $seller = Seller::where('user_id', $sellerUserId)->first();
        if (! $seller) {
            return response()->json(['message' => 'Store not found.'], 404);
        }

        $reviews = Review::with(['buyer', 'product'])
            ->where('status', 'visible')
            ->whereIn('product_id', function ($q) use ($sellerUserId) {
                $q->select('id')->from('products')->where('seller_id', $sellerUserId);
            })
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json(['reviews' => $reviews]);
    }

    /**
     * Toggle "follow store" for the logged-in buyer.
     * Returns the new state and follower count.
     */
    public function follow(Request $request, $sellerUserId)
    {
        $seller = Seller::where('user_id', $sellerUserId)->first();
        if (! $seller) {
            return response()->json(['message' => 'Store not found.'], 404);
        }

        $buyerId = $request->user()->id;
        if ($buyerId === (int) $sellerUserId) {
            return response()->json(['message' => 'You cannot follow your own store.'], 422);
        }

        $existing = StoreFollow::where('buyer_id', $buyerId)
            ->where('seller_id', $sellerUserId)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json([
                'message'   => 'Unfollowed store.',
                'is_following' => false,
                'followers' => StoreFollow::where('seller_id', $sellerUserId)->count(),
            ]);
        }

        StoreFollow::create([
            'buyer_id'   => $buyerId,
            'seller_id'  => $sellerUserId,
        ]);

        return response()->json([
            'message'   => 'Store followed.',
            'is_following' => true,
            'followers' => StoreFollow::where('seller_id', $sellerUserId)->count(),
        ]);
    }

    /**
     * Build the store profile object (stats, rating breakdown, follow state).
     */
    protected function storeInfo(Request $request, int $sellerUserId, int $productCount): array
    {
        $seller = Seller::where('user_id', $sellerUserId)->first();
        $user = User::find($sellerUserId);

        $avgRating = round((float) Product::where('seller_id', $sellerUserId)
            ->where('status', 'active')
            ->avg('rating'), 1);

        // Total units sold across the store (non-cancelled order items).
        $totalSold = (int) OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('products.seller_id', $sellerUserId)
            ->where('orders.status', '!=', 'cancelled')
            ->sum('order_items.quantity');

        // Follower count + whether the current buyer follows.
        $followers = StoreFollow::where('seller_id', $sellerUserId)->count();
        $isFollowing = false;
        $buyer = auth('sanctum')->user();
        if ($buyer) {
            $isFollowing = StoreFollow::where('buyer_id', $buyer->id)
                ->where('seller_id', $sellerUserId)
                ->exists();
        }

        // Review count + star distribution for the store (across its products).
        $reviewAgg = Review::query()
            ->where('status', 'visible')
            ->whereIn('product_id', function ($q) use ($sellerUserId) {
                $q->select('id')->from('products')->where('seller_id', $sellerUserId);
            })
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(rating = 5),0) as r5, COALESCE(SUM(rating = 4),0) as r4, COALESCE(SUM(rating = 3),0) as r3, COALESCE(SUM(rating = 2),0) as r2, COALESCE(SUM(rating = 1),0) as r1')
            ->first();

        $reviewsCount = (int) ($reviewAgg->total ?? 0);

        return [
            'seller_id'        => $user->id,
            'business_name'    => $seller->business_name,
            'line_of_business' => $seller->line_of_business,
            'owner_name'       => $user->full_name,
            'rating'           => $avgRating,
            'product_count'    => $productCount,
            'total_sold'       => $totalSold,
            'followers'        => $followers,
            'is_following'     => $isFollowing,
            'review_count'     => $reviewsCount,
            'rating_breakdown' => $reviewsCount > 0 ? [
                '5' => (int) ($reviewAgg->r5 ?? 0),
                '4' => (int) ($reviewAgg->r4 ?? 0),
                '3' => (int) ($reviewAgg->r3 ?? 0),
                '2' => (int) ($reviewAgg->r2 ?? 0),
                '1' => (int) ($reviewAgg->r1 ?? 0),
            ] : [],
            'member_since'     => $seller->created_at?->toDateString(),
            'logo'             => null,
        ];
    }
}
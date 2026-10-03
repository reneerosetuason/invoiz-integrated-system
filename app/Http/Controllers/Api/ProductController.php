<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Seller;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query()
            ->with(['category', 'variants'])
            ->where('status', 'active');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->has('search') && $request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->input('search').'%')
                    ->orWhere('description', 'like', '%'.$request->input('search').'%');
            });
        }

        if ($request->has('sort')) {
            switch ($request->input('sort')) {
                case 'price_asc':
                    $query->orderBy('price', 'asc');
                    break;
                case 'price_desc':
                    $query->orderBy('price', 'desc');
                    break;
                case 'rating':
                    $query->orderByDesc('rating');
                    break;
                default:
                    $query->latest();
            }
        } else {
            $query->latest();
        }

        $products = $query->paginate($request->integer('per_page', 12));

        $counts = $this->soldCountFor($products->pluck('id')->all());
        $products->getCollection()->each(function ($product) use ($counts) {
            $product->sold = $counts[$product->id] ?? 0;
        });

        $this->attachShop($products->getCollection());

        return response()->json($products);
    }

    public function show(Request $request, $id)
    {
        $product = Product::with(['category', 'seller', 'variants', 'images', 'reviews.buyer'])
            ->where('status', 'active')
            ->findOrFail($id);

        // Attach whether the logged-in buyer (if any) has this product in favorites.
        $product->is_favorited = false;
        if ($request->user()) {
            $product->is_favorited = $product->favorites()
                ->where('buyer_id', $request->user()->id)
                ->exists();
        }

        $product->sold = $this->soldCountFor([$id])[$id] ?? 0;

        $this->attachShop(collect([$product]));
        $this->attachGallery(collect([$product]));

        return response()->json(['product' => $product]);
    }

    /**
     * Attach a `gallery` array (all product photos, main first) so the UI can
     * render a swipeable image gallery. Falls back to the single cover image.
     */
    protected function attachGallery($products): void
    {
        foreach ($products as $product) {
            $images = $product->images->pluck('image_path')->all();

            $gallery = [];
            if (! empty($images)) {
                $gallery = $images;
            } elseif ($product->image) {
                $gallery = [$product->image];
            }

            $product->gallery = $gallery;
        }
    }

    /**
     * Attach a lightweight `shop` object (business name, rating, product count)
     * so buyer UIs can show "Sold by <shop>" and link to the store page.
     */
    protected function attachShop($products): void
    {
        $sellerIds = $products->pluck('seller_id')->unique()->values()->all();

        if (empty($sellerIds)) {
            return;
        }

        $sellers = Seller::whereIn('user_id', $sellerIds)->get()->keyBy('user_id');

        $counts = Product::whereIn('seller_id', $sellerIds)
            ->where('status', 'active')
            ->selectRaw('seller_id, COUNT(*) as c')
            ->groupBy('seller_id')
            ->pluck('c', 'seller_id');

        $ratings = Product::whereIn('seller_id', $sellerIds)
            ->where('status', 'active')
            ->selectRaw('seller_id, AVG(rating) as r')
            ->groupBy('seller_id')
            ->pluck('r', 'seller_id');

        $followers = \App\Models\StoreFollow::whereIn('seller_id', $sellerIds)
            ->selectRaw('seller_id, COUNT(*) as c')
            ->groupBy('seller_id')
            ->pluck('c', 'seller_id');

        foreach ($products as $product) {
            $store = $sellers[$product->seller_id] ?? null;

            $product->shop = [
                'seller_id'      => $product->seller_id,
                'business_name'  => $store->business_name ?? 'Invoiz Store',
                'line_of_business' => $store->line_of_business ?? '',
                'rating'         => round((float) ($ratings[$product->seller_id] ?? 0), 1),
                'product_count'  => (int) ($counts[$product->seller_id] ?? 0),
                'followers'      => (int) ($followers[$product->seller_id] ?? 0),
                'logo'           => null,
            ];
        }
    }

    /**
     * Count how many units of each product were actually sold,
     * i.e. quantity in order items whose order is not cancelled.
     */
    protected function soldCountFor(array $productIds)
    {
        if (empty($productIds)) {
            return [];
        }

        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('order_items.product_id', $productIds)
            ->where('orders.status', '!=', 'cancelled')
            ->selectRaw('order_items.product_id, SUM(order_items.quantity) as total')
            ->groupBy('order_items.product_id')
            ->pluck('total', 'product_id')
            ->map(fn ($v) => (int) $v)
            ->toArray();
    }
}
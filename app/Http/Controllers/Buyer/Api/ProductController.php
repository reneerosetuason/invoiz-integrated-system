<?php

namespace App\Http\Controllers\Buyer\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Favorite;
use App\Models\Review;
use App\Models\Seller;
use App\Models\StoreFollow;
use App\Support\Sales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['per_page' => 'sometimes|integer|min:1|max:100', 'page' => 'sometimes|integer|min:1', 'search' => 'sometimes|string|max:200', 'category_id' => 'sometimes|integer|min:1', 'ids' => ['sometimes', 'string', 'max:251', 'regex:/^[1-9][0-9]{0,19}(,[1-9][0-9]{0,19}){0,11}$/']]);
        $query = Product::query()
            ->with(['category', 'variants', 'images'])
            ->storefront()
            ->select('products.*')
            ->selectSub(Review::query()->selectRaw('AVG(rating)')->whereColumn('reviews.product_id', 'products.id')->where('status', 'visible'), 'visible_rating')
            ->selectSub(Review::query()->selectRaw('COUNT(*)')->whereColumn('reviews.product_id', 'products.id')->where('status', 'visible'), 'visible_reviews_count');

        if ($request->filled('ids')) {
            $query->whereIn('products.id', explode(',', $request->string('ids')->toString()));
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->has('search') && $request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->input('search').'%')
                    ->orWhere('description', 'like', '%'.$request->input('search').'%');
            });
        }

        // Preserve the mobile sale filter and include the shared seller sales.
        if ($request->boolean('on_sale') || $request->input('sort') === 'discount') {
            $sale = DB::table('product_sales')->join('sales', 'sales.id', '=', 'product_sales.sale_id')
                ->whereColumn('product_sales.product_id', 'products.id')
                ->whereNull('product_sales.deleted_at')->whereNull('sales.deleted_at')
                ->where('sales.status', 'active')
                ->where(fn ($q) => $q->whereNull('sales.starts_at')->orWhere('sales.starts_at', '<=', now()))
                ->where(fn ($q) => $q->whereNull('sales.ends_at')->orWhere('sales.ends_at', '>=', now()))
                ->orderByDesc('sales.starts_at')->limit(1);
            $query->selectSub((clone $sale)->selectRaw("CASE WHEN sales.discount_type = 'fixed' THEN sales.discount_value ELSE products.price * sales.discount_value / 100.0 END"), 'active_sale_discount');
            $query->where(fn ($q) => $q->where(fn ($q) => $q->whereNotNull('compare_at_price')->whereColumn('compare_at_price', '>', 'price'))
                ->orWhereExists((clone $sale)->selectRaw('1')));
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
                    $query->orderByRaw('COALESCE(visible_rating, rating, 0) DESC')->orderByDesc('visible_reviews_count');
                    break;
                case 'most_reviewed':
                    $query->orderByDesc('visible_reviews_count')->orderByRaw('COALESCE(visible_rating, rating, 0) DESC');
                    break;
                case 'discount':
                    $reference = '(CASE WHEN compare_at_price > price THEN compare_at_price ELSE price END)';
                    $effective = '(CASE WHEN COALESCE(active_sale_discount, 0) > price THEN 0 ELSE price - COALESCE(active_sale_discount, 0) END)';
                    $query->orderByRaw("CASE WHEN {$reference} > 0 THEN ({$reference} - {$effective}) * 1.0 / {$reference} ELSE 0 END DESC");
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
        $this->attachGallery($products->getCollection());
        $this->attachSale($products->getCollection());

        $favoriteIds = $request->user()
            ? Favorite::where('buyer_id', $request->user()->id)->whereIn('product_id', $products->pluck('id'))->pluck('product_id')->map(fn ($id) => (int) $id)->all()
            : [];
        $products->getCollection()->each(fn ($product) => $product->setAttribute('is_favorited', in_array($product->id, $favoriteIds, true)));

        return response()->json($products);
    }

    public function show(Request $request, $id)
    {
        $product = Product::with([
            'category',
            'seller:id,user_id,store_name,business_name,line_of_business,logo',
            'variants',
            'images',
            'reviews' => fn ($query) => $query->where('status', 'visible')->with('buyer:id,first_name,last_name,name'),
        ])
            ->storefront()
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
        $this->attachSale(collect([$product]));

        return response()->json(['product' => $product]);
    }

    /**
     * Attach a `gallery` array (all product photos, main first) so the UI can
     * render a swipeable image gallery. Falls back to the single cover image.
     */
    protected function attachGallery($products): void
    {
        foreach ($products as $product) {
            $images = $product->images
                ->sortBy(fn ($image) => [! $image->is_main, (int) $image->sort_order, $image->id])
                ->map(fn ($image) => $image->resolved_path);
            $product->gallery = collect([$product->image])->merge($images)
                ->filter(fn ($path) => is_string($path) && trim($path) !== '')
                ->map(fn ($path) => trim($path))->unique()->values()->all();
        }
    }

    /**
     * Attach running-sale info so buyer UIs can show the discounted price.
     */
    protected function attachSale($products): void
    {
        foreach ($products as $product) {
            $sale = Sales::saleFor($product);
            $product->on_sale = (bool) $sale;
            $product->effective_price = Sales::priceFor($product);
            $product->sale_price = $sale ? $product->effective_price : null;
            $product->sale = $sale ? [
                'name' => $sale->name,
                'discount_type' => $sale->discount_type,
                'discount_value' => $sale->discount_value,
                'ends_at' => $sale->ends_at,
            ] : null;
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

        $followers = StoreFollow::whereIn('seller_id', $sellerIds)
            ->selectRaw('seller_id, COUNT(*) as c')
            ->groupBy('seller_id')
            ->pluck('c', 'seller_id');

        foreach ($products as $product) {
            $store = $sellers[$product->seller_id] ?? null;

            $product->shop = [
                'seller_id' => $product->seller_id,
                'business_name' => $store->business_name ?? 'Invoiz Store',
                'line_of_business' => $store->line_of_business ?? '',
                'rating' => round((float) ($ratings[$product->seller_id] ?? 0), 1),
                'product_count' => (int) ($counts[$product->seller_id] ?? 0),
                'followers' => (int) ($followers[$product->seller_id] ?? 0),
                'logo' => $store?->logo,
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

<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Models\Product;
use Illuminate\Http\Request;

// ---- Landing (live marketplace data) ----
Route::get('/', function (Request $request) {
    $deals = [];
    $categories = collect();
    $stores = collect();
    $sort = $request->input('sort', 'recommended');
    $allowedSorts = ['recommended', 'newest', 'price_asc', 'price_desc', 'rating', 'most_reviewed'];
    if (! in_array($sort, $allowedSorts, true)) $sort = 'recommended';
    $buyerId = session('buyer.id') ?? (Auth::check() ? Auth::id() : null);
    $favoriteIds = $buyerId ? \App\Models\Favorite::where('buyer_id', $buyerId)->pluck('product_id')->map(fn ($id) => (int) $id)->all() : [];
    $stats = ['products' => 0, 'sellers' => 0, 'orders' => 0];
    try {
        $dealQuery = Product::with('category')->where('status', 'active')
            ->select('products.*')
            ->selectSub(\App\Models\Review::query()->selectRaw('AVG(rating)')->whereColumn('reviews.product_id', 'products.id')->where('status', 'visible'), 'visible_rating')
            ->selectSub(\App\Models\Review::query()->selectRaw('COUNT(*)')->whereColumn('reviews.product_id', 'products.id')->where('status', 'visible'), 'visible_reviews_count');
        $dealFilter = (clone $dealQuery)
            ->whereNotNull('compare_at_price')
            ->whereColumn('compare_at_price', '>', 'price');
        $hasDeals = (clone $dealFilter)->exists();
        $dealRows = $dealFilter;
        if (! $hasDeals) {
            $dealRows = clone $dealQuery;
        }
        switch ($sort) {
            case 'price_asc': $dealRows->orderBy('price')->orderByDesc('created_at'); break;
            case 'price_desc': $dealRows->orderByDesc('price')->orderByDesc('created_at'); break;
            case 'rating': $dealRows->orderByRaw('COALESCE(visible_rating, rating, 0) DESC')->orderByDesc('visible_reviews_count')->orderByDesc('created_at'); break;
            case 'most_reviewed': $dealRows->orderByDesc('visible_reviews_count')->orderByRaw('COALESCE(visible_rating, rating, 0) DESC')->orderByDesc('created_at'); break;
            default: $dealRows->orderByDesc('created_at');
        }
        $dealRows = $dealRows->take(6)->get();
        $deals = $dealRows->map(function ($p) {
            $pct = ($p->compare_at_price > $p->price && $p->compare_at_price > 0)
                ? (int) round((($p->compare_at_price - $p->price) / $p->compare_at_price) * 100) : 0;
            try { $store = \App\Models\Seller::nameFor(\App\Models\Seller::userIdFor((int) $p->seller_id)); } catch (\Throwable $e) { $store = ''; }
            return [
                'id' => $p->id, 'name' => $p->name,
                'price' => (float) ($p->price ?? 0),
                'compare_at' => (float) ($p->compare_at_price ?? 0),
                'pct' => $pct, 'rating' => ($p->visible_rating ?? $p->rating) ? round((float) ($p->visible_rating ?? $p->rating), 1) : null,
                'store' => $store, 'image' => $p->primary_image_url,
            ];
        })->toArray();
        $categories = \App\Models\Category::active()->withCount(['products' => fn ($q) => $q->where('status', 'active')])->orderByDesc('products_count')->take(10)->get()->map(function ($c) {
            $imgs = Product::where('category_id', $c->id)->where('status', 'active')->orderByDesc('created_at')->take(8)->get()
                ->map(fn ($p) => $p->primary_image_url)->filter()->unique()->take(4)->values()->all();
            $c->cover_images = $imgs;
            return $c;
        });
        $sellers = \App\Models\Seller::approved()->with('user')->get();
        $stores = $sellers->map(function ($s) {
            $uid = $s->user_id;
            $count = Product::where(function ($q) use ($s, $uid) { $q->where('seller_id', $uid)->orWhere('seller_id', $s->id); })->where('status', 'active')->count();
            $logo = null;
            try {
                if ($s->logo && file_exists(storage_path('app/public/' . ltrim($s->logo, '/')))) {
                    $logo = asset('storage/' . ltrim($s->logo, '/'));
                }
            } catch (\Throwable $e) {}
            return ['user_id' => $uid, 'name' => $s->display_name, 'line' => $s->line_of_business ?: 'Local seller', 'products' => $count, 'logo' => $logo];
        })->sortByDesc('products')->take(3)->values();
        $stats = [
            'products' => Product::where('status', 'active')->count(),
            'sellers' => $sellers->count(),
            'orders' => \App\Models\Order::whereNotIn('status', ['cancelled'])->count(),
        ];
    } catch (\Throwable $e) { $deals = []; }
    return view('buyer.landing', compact('deals', 'categories', 'stores', 'stats', 'favoriteIds', 'sort'));
})->name('landing');

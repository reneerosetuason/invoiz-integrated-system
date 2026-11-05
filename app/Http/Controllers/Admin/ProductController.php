<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        // Admin sees ALL products (including orphaned ones needing reassignment).
        // The storefront (StoreController) still only exposes approved sellers.
        $query = Product::with(['seller', 'category', 'images']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category') && $request->category !== 'all') {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category)->orWhere('name', $request->category);
                if (ctype_digit((string) $request->category)) { $q->orWhere('id', (int) $request->category); }
            });
        }

        $products = $query->orderBy('created_at', 'desc')->orderByDesc('id')->paginate(10)->withQueryString();
        $grouped = $products->getCollection()->groupBy(function ($p) { return $p->category->name ?? 'Uncategorized'; })->sortKeys();
        // Stats split: shop-visible (approved sellers) vs needing attention.
        $categories = \App\Models\Category::withCount('products')->orderBy('name')->get();
        $stats = [
            'all' => Product::count(),
            'visible' => Product::whereHas('seller', fn ($q) => $q->approved())->count(),
            'orphaned' => Product::whereDoesntHave('seller')->count(),
            'active' => Product::where('status', 'active')->count(),
            'suspended' => Product::where('status', 'suspended')->count(),
            'categories' => \App\Models\Category::count(),
            'hidden_pending' => Product::whereDoesntHave('seller', fn ($q) => $q->approved())->count(),
        ];
        $featuredIds = \App\Support\LandingFeatures::ids();
        return view('admin.products', compact('products', 'grouped', 'categories', 'stats', 'featuredIds'));
    }

    public function feature(Request $request, Product $product)
    {
        $request->validate(['featured' => ['required', 'boolean']]);
        if ($request->boolean('featured') && ! Product::storefront()->whereKey($product->id)->exists()) {
            return back()->withErrors(['featured' => 'Only active products from approved, active sellers can be featured.']);
        }
        \App\Models\Setting::set('landing_featured_product_'.$product->id, $request->boolean('featured') ? '1' : '0');
        return back()->with('status', $request->boolean('featured') ? 'Product added to the landing-page banner.' : 'Product removed from the landing-page banner.');
    }

    public function updateStatus(Request $request, Product $product)
    {
        $request->validate([
            'status' => 'required|in:active,inactive,suspended',
        ]);

        $product->status = $request->status;
        $product->save();

        return redirect()->back()->with('status', "Product status updated to {$request->status}.");
    }
}

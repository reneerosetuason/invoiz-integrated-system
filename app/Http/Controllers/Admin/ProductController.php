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
            });
        }

        $products = $query->orderBy('created_at', 'desc')->get();
        $grouped = $products->groupBy(function ($p) { return $p->category->name ?? 'Uncategorized'; })->sortKeys();
        // Stats split: shop-visible (approved sellers) vs needing attention.
        $categories = \App\Models\Category::withCount(['products' => function ($q) {
            $q->whereHas('seller', fn ($qq) => $qq->approved());
        }])->orderBy('name')->get();
        $stats = [
            'all' => Product::count(),
            'visible' => Product::whereHas('seller', fn ($q) => $q->approved())->count(),
            'orphaned' => Product::whereDoesntHave('seller')->count(),
            'active' => Product::where('status', 'active')->count(),
            'suspended' => Product::where('status', 'suspended')->count(),
            'categories' => \App\Models\Category::count(),
            'hidden_pending' => Product::whereDoesntHave('seller', fn ($q) => $q->approved())->count(),
        ];
        return view('admin.products', compact('products', 'grouped', 'categories', 'stats'));
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

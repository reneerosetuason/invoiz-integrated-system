<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('seller')->where('status', 'active');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('seller_id')) {
            $query->where('seller_id', $request->seller_id);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $sort = $request->string('sort', 'newest');
        match ($sort) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'name_asc' => $query->orderBy('name', 'asc'),
            'name_desc' => $query->orderBy('name', 'desc'),
            default => $query->orderBy('created_at', 'desc'),
        };

        $products = $query->paginate(10);
        $this->attachShop($products);

        return $products;
    }

    public function show(Product $product)
    {
        $product->load('seller', 'images', 'variants');
        $this->attachShop($product);

        return $product;
    }

    protected function attachShop($products)
    {
        if ($products instanceof \Illuminate\Pagination\AbstractPaginator) {
            $products->loadMissing('seller');
            $products->getCollection()->transform(function ($product) {
                $product->loadMissing('seller');
                return $product;
            });
        } else {
            $products->loadMissing('seller');
        }

        return $products;
    }
}
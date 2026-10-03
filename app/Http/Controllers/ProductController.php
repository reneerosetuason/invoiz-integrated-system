<?php

namespace App\Http\Controllers;

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

        $products = $query->paginate(20);
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
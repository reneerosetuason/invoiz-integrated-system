<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::where('active', 1)
            ->withCount('products')
            ->orderBy('name')
            ->get();

        return response()->json(['categories' => $categories]);
    }
}
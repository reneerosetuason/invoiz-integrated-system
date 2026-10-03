<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $favorites = Favorite::with('product.category')
            ->where('buyer_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json(['favorites' => $favorites]);
    }

    public function toggle(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
        ]);

        $existing = Favorite::where('buyer_id', $request->user()->id)
            ->where('product_id', $validated['product_id'])
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['message' => 'Removed from favorites.', 'is_favorited' => false]);
        }

        Favorite::create([
            'buyer_id'   => $request->user()->id,
            'product_id' => $validated['product_id'],
        ]);

        return response()->json(['message' => 'Added to favorites.', 'is_favorited' => true]);
    }
}
<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;

use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $sellerId = auth()->id();

        $query = Review::with(['buyer', 'product'])
            ->whereHas('product', fn ($q) => $q->where('seller_id', $sellerId))
            ->latest();

        if ($request->filled('rating')) {
            $query->where('rating', $request->input('rating'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $reviews = $query->get();

        $stats = [
            'avg'         => round($reviews->avg('rating') ?? 0, 1),
            'total'       => $reviews->count(),
            'five'        => $reviews->where('rating', 5)->count(),
            'four'        => $reviews->where('rating', 4)->count(),
            'three'       => $reviews->where('rating', 3)->count(),
            'two'         => $reviews->where('rating', 2)->count(),
            'one'         => $reviews->where('rating', 1)->count(),
        ];

        return view('seller.reviews.index', [
            'reviews' => $reviews,
            'stats'   => $stats,
            'filters' => $request->only(['rating', 'status']),
        ]);
    }

    public function toggle($id)
    {
        $review = Review::whereHas('product', fn ($q) => $q->where('seller_id', auth()->id()))
            ->findOrFail($id);

        $review->update([
            'status' => $review->status === 'visible' ? 'hidden' : 'visible',
        ]);

        // Keep the cached product rating in sync.
        $avg = Review::where('product_id', $review->product_id)
            ->where('status', 'visible')
            ->avg('rating');
        Product::where('id', $review->product_id)->update([
            'rating' => $avg ? round($avg, 1) : null,
        ]);

        return back()->with('success', $review->status === 'visible'
            ? 'Feedback shown on the product.'
            : 'Feedback hidden from the product.');
    }
}
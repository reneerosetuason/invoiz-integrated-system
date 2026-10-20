<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;

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

        // Stat cards use the full filtered set: compute BEFORE paginate()
        // (paginate adds limit/offset to the builder).
        $stats = [
            'avg'         => round((clone $query)->avg('rating') ?? 0, 1),
            'total'       => (clone $query)->count(),
            'five'        => (clone $query)->where('rating', 5)->count(),
            'four'        => (clone $query)->where('rating', 4)->count(),
            'three'       => (clone $query)->where('rating', 3)->count(),
            'two'         => (clone $query)->where('rating', 2)->count(),
            'one'         => (clone $query)->where('rating', 1)->count(),
        ];

        $reviews = $query->paginate(10);

        return view('seller.reviews.index', [
            'reviews' => $reviews,
            'stats'   => $stats,
            'filters' => $request->only(['rating', 'status']),
        ]);
    }

    public function reply(Request $request, $id)
    {
        $review = Review::whereHas('product', fn ($q) => $q->where('seller_id', auth()->id()))
            ->findOrFail($id);

        $validated = $request->validate([
            'seller_reply' => ['required', 'string', 'min:2', 'max:1000'],
        ]);

        $review->update([
            'seller_reply'      => $validated['seller_reply'],
            'seller_replied_at' => now(),
        ]);

        return back()->with('success', 'Your reply is now visible on the product page.');
    }
}
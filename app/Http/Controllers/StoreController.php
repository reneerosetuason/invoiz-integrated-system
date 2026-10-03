<?php

namespace App\Http\Controllers;

use App\Models\Seller;
use App\Models\Product;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function index(Request $request)
    {
        $query = Seller::with('user')->approved();

        if ($request->filled('search')) {
            $query->where('store_name', 'like', "%{$request->search}%");
        }

        $stores = $query->paginate(20);

        return $stores->through(function ($seller) {
            return $this->storePayload($seller);
        });
    }

    public function show(Seller $seller)
    {
        if (! $seller->isApproved()) {
            return response()->json(['message' => 'Store not available'], 404);
        }

        $seller->load('user');

        return $this->storePayload($seller, true);
    }

    protected function storePayload(Seller $seller, bool $withProducts = false)
    {
        $payload = [
            'id' => $seller->id,
            'store_name' => $seller->store_name,
            'primary_color' => $seller->primary_color,
            'accent_color' => $seller->accent_color,
            'logo' => $seller->logoUrl(),
            'initial' => mb_strtoupper(mb_substr($seller->store_name, 0, 1)),
            'status' => $seller->status,
        ];

        if ($withProducts) {
            $payload['products'] = Product::with('seller')
                ->where('seller_id', $seller->id)
                ->where('status', 'active')
                ->get();
        }

        return $payload;
    }
}
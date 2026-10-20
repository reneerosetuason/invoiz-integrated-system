<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\Sale;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    public function index()
    {
        $seller = $this->sellerRecord();
        $sellerIds = $seller->catalogIds();

        $sales = Sale::whereIn('seller_id', $sellerIds)
            ->with('items')
            ->latest()
            ->paginate(10);

        $products = Product::whereIn('seller_id', $sellerIds)
            ->where('status', '!=', 'draft')
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'seller_id']);

        $activeSale = Sale::whereIn('seller_id', $sellerIds)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->first();

        return view('seller.sales.index', compact('sales', 'products', 'activeSale'));
    }

    public function store(Request $request)
    {
        $sellerIds = $this->sellerRecord()->catalogIds();

        $validated = $this->validateSale($request);

        if ($validated['status'] === 'active' && $this->hasLiveSale($sellerIds, null)) {
            return back()->withInput()->with('error', 'You already have a running sale. A store can only run one sale at a time.');
        }

        DB::transaction(function () use ($validated, $request, $sellerIds) {
            $sale = Sale::create([
                'seller_id'      => $this->sellerRecord()->id,
                'name'           => $validated['name'],
                'type'           => 'store',
                'discount_type'  => $validated['discount_type'],
                'discount_value' => $validated['discount_value'],
                'starts_at'      => $validated['starts_at'] ?? null,
                'ends_at'        => $validated['ends_at'] ?? null,
                'status'         => $validated['status'],
            ]);

            $this->syncProducts($sale, $request, $sellerIds);
        });

        return back()->with('success', 'Sale created successfully.');
    }

    public function update(Request $request, $id)
    {
        $sellerIds = $this->sellerRecord()->catalogIds();
        $sale = Sale::whereIn('seller_id', $sellerIds)->findOrFail($id);

        $validated = $this->validateSale($request);

        if ($validated['status'] === 'active' && $this->hasLiveSale($sellerIds, $sale->id)) {
            return back()->withInput()->with('error', 'You already have another running sale. A store can only run one sale at a time.');
        }

        DB::transaction(function () use ($sale, $validated, $request, $sellerIds) {
            $sale->update([
                'name'           => $validated['name'],
                'discount_type'  => $validated['discount_type'],
                'discount_value' => $validated['discount_value'],
                'starts_at'      => $validated['starts_at'] ?? null,
                'ends_at'        => $validated['ends_at'] ?? null,
                'status'         => $validated['status'],
            ]);

            $this->syncProducts($sale, $request, $sellerIds);
        });

        return back()->with('success', 'Sale updated successfully.');
    }

    public function destroy($id)
    {
        $sale = Sale::whereIn('seller_id', $this->sellerRecord()->catalogIds())->findOrFail($id);
        $sale->items()->delete();
        $sale->delete();

        return back()->with('success', 'Sale deleted.');
    }

    private function validateSale(Request $request): array
    {
        return $request->validate([
            'name'           => ['required', 'string', 'max:120'],
            'discount_type'  => ['required', 'in:percent,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'starts_at'      => ['nullable', 'date'],
            'ends_at'        => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status'         => ['required', 'in:active,inactive'],
            'products'       => ['required', 'array', 'min:1'],
            'products.*'     => ['integer'],
        ]);
    }

    private function syncProducts(Sale $sale, Request $request, array $sellerIds): void
    {
        $productIds = Product::whereIn('seller_id', $sellerIds)
            ->whereIn('id', (array) $request->input('products', []))
            ->pluck('id')
            ->all();

        $sale->items()->whereNotIn('product_id', $productIds ?: [0])->delete();

        foreach ($productIds as $pid) {
            ProductSale::updateOrCreate(
                ['sale_id' => $sale->id, 'product_id' => $pid],
                ['sale_price' => null]
            );
        }
    }

    private function hasLiveSale(array $sellerIds, ?int $exceptId): bool
    {
        return Sale::whereIn('seller_id', $sellerIds)
            ->where('status', 'active')
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->exists();
    }

    private function sellerRecord(): Seller
    {
        return Seller::where('user_id', auth()->id())->firstOrFail();
    }
}

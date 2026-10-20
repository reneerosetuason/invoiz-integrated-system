<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $sellerId = auth()->id();

        $query = Product::with('category')
            ->forSeller($sellerId)
            ->withCount('variants');

        if ($request->filled('q')) {
            $query->where('name', 'like', '%'.$request->input('q').'%');
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('low_stock')) {
            $query->whereRaw('stock <= COALESCE(low_stock_threshold, 5)');
        }

        $products = $query->latest()->paginate(10);

        $categories = Category::where('status', 'active')->orderBy('name')->get();

        return view('seller.products.index', [
            'products'   => $products,
            'categories' => $categories,
            'filters'    => $request->only(['q', 'category_id', 'status', 'low_stock']),
        ]);
    }

    public function create()
    {
        $categories = Category::where('status', 'active')->orderBy('name')->get();

        return view('seller.products.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'min:5', 'max:120'],
            'category_id'  => ['required', 'exists:categories,id'],
            'description'  => ['nullable', 'string', 'min:20', 'max:3000'],
            'price'        => ['required', 'numeric', 'min:1', 'max:1000000', 'decimal:0,2'],
            'cost_price'   => ['nullable', 'numeric', 'min:0'],
            'stock'        => ['required', 'integer', 'min:0', 'max:99999'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'weight'       => ['nullable', 'string', 'max:50'],
            'dimensions'   => ['nullable', 'string', 'max:100'],
        'image'        => ['nullable', 'image', 'max:5120'],
        'images'       => ['nullable', 'array', 'max:9'],
        'images.*'     => ['image', 'mimes:jpg,jpeg,png', 'max:5120'],
        'status'       => ['required', 'in:' . implode(',', $this->allowedStatuses())],
        ]);

        $validated['seller_id'] = auth()->id();
        $validated['low_stock_threshold'] ??= 5;

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        $product = DB::transaction(function () use ($validated, $request) {
            $product = Product::create($validated);

            $this->saveVariants($product, $request);
            \App\Support\ProductImages::save($product, $request);

            return $product;
        });

        \App\Support\Stock::checkLowStock($product->id);

        return redirect()->route('seller.products.edit', $product->id)
            ->with('success', 'Product added successfully.');
    }

    public function edit($id)
    {
        $product = Product::forSeller(auth()->id())
            ->with('variants')
            ->findOrFail($id);

        $categories = Category::where('status', 'active')->orderBy('name')->get();

        return view('seller.products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, $id)
    {
        $product = Product::forSeller(auth()->id())->findOrFail($id);

        $validated = $request->validate([
            'name'         => ['required', 'string', 'min:5', 'max:120'],
            'category_id'  => ['required', 'exists:categories,id'],
            'description'  => ['nullable', 'string', 'min:20', 'max:3000'],
            'price'        => ['required', 'numeric', 'min:1', 'max:1000000', 'decimal:0,2'],
            'cost_price'   => ['nullable', 'numeric', 'min:0'],
            'stock'        => ['required', 'integer', 'min:0', 'max:99999'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'weight'       => ['nullable', 'string', 'max:50'],
            'dimensions'   => ['nullable', 'string', 'max:100'],
        'image'        => ['nullable', 'image', 'max:5120'],
        'images'       => ['nullable', 'array', 'max:9'],
        'images.*'     => ['image', 'mimes:jpg,jpeg,png', 'max:5120'],
        'status'       => ['required', 'in:' . implode(',', $this->allowedStatuses())],
        ]);

        $validated['low_stock_threshold'] ??= 5;

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        DB::transaction(function () use ($product, $validated, $request) {
            $product->update($validated);
            $this->saveVariants($product, $request);
            \App\Support\ProductImages::save($product, $request);
        });

        // Alert (or resolve) based on the new stock / alert level,
        // including per-variation stock.
        \App\Support\Stock::checkLowStock($product->id);
        foreach ($product->variants()->get(['id']) as $variant) {
            \App\Support\Stock::checkLowStock($product->id, $variant->id);
        }

        return back()->with('success', 'Product updated successfully.');
    }

    public function archive($id)
    {
        $product = Product::forSeller(auth()->id())->findOrFail($id);
        $product->update(['status' => 'inactive']);

        return back()->with('success', 'Product archived.');
    }

    public function restore($id)
    {
        $product = Product::forSeller(auth()->id())->findOrFail($id);
        $product->update(['status' => 'active']);

        return back()->with('success', 'Product restored.');
    }

    private function allowedStatuses(): array
    {
        $seller = \App\Models\Seller::where('user_id', auth()->id())->first();
        $approved = $seller && $seller->approval_status === 'approved';

        // Pending sellers can only save drafts; approved sellers publish.
        return $approved ? ['active', 'inactive', 'out_of_stock'] : ['draft'];
    }

    private function saveVariants(Product $product, Request $request): void
    {
        $types = $request->input('variants.type', []);
        $values = $request->input('variants.value', []);
        $adjustments = $request->input('variants.adjustment', []);
        $stocks = $request->input('variants.stock', []);

        if (empty($types) || empty(array_filter($types))) {
            return;
        }

        $product->variants()->delete();

        foreach ($types as $index => $type) {
            $value = trim($values[$index] ?? '');
            if ($type === null || trim((string) $type) === '' || $value === '') {
                continue;
            }

            $product->variants()->create([
                'variant_type'     => trim($type),
                'variant_value'    => $value,
                'price_adjustment' => (float) ($adjustments[$index] ?? 0),
                'stock'            => (int) ($stocks[$index] ?? 0),
                'status'           => 'active',
            ]);
        }

        // Keep the product status in sync with variant stock.
        $totalVariantStock = $product->variants()->sum('stock');
        if ($totalVariantStock > 0 && $product->status === 'out_of_stock') {
            $product->update(['status' => 'active']);
        }
    }
}
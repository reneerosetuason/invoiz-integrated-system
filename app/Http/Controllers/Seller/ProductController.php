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
            $query->where('stock', '<=', 5);
        }

        $products = $query->latest()->get();

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
            'name'         => ['required', 'string', 'max:150'],
            'category_id'  => ['required', 'exists:categories,id'],
            'description'  => ['nullable', 'string', 'max:5000'],
            'price'        => ['required', 'numeric', 'min:0'],
            'cost_price'   => ['nullable', 'numeric', 'min:0'],
            'stock'        => ['required', 'integer', 'min:0'],
            'weight'       => ['nullable', 'string', 'max:50'],
            'dimensions'   => ['nullable', 'string', 'max:100'],
            'image'        => ['nullable', 'image', 'max:5120'],
            'status'       => ['required', 'in:active,inactive,out_of_stock'],
        ]);

        $validated['seller_id'] = auth()->id();

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        $product = DB::transaction(function () use ($validated, $request) {
            $product = Product::create($validated);

            $this->saveVariants($product, $request);

            return $product;
        });

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
            'name'         => ['required', 'string', 'max:150'],
            'category_id'  => ['required', 'exists:categories,id'],
            'description'  => ['nullable', 'string', 'max:5000'],
            'price'        => ['required', 'numeric', 'min:0'],
            'cost_price'   => ['nullable', 'numeric', 'min:0'],
            'stock'        => ['required', 'integer', 'min:0'],
            'weight'       => ['nullable', 'string', 'max:50'],
            'dimensions'   => ['nullable', 'string', 'max:100'],
            'image'        => ['nullable', 'image', 'max:5120'],
            'status'       => ['required', 'in:active,inactive,out_of_stock'],
        ]);

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        DB::transaction(function () use ($product, $validated, $request) {
            $product->update($validated);
            $this->saveVariants($product, $request);
        });

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
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

        $this->variantRows($request, (int) $validated['stock']);

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

        $this->variantRows($request, (int) $validated['stock'], $product);

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        DB::transaction(function () use ($product, $validated, $request) {
            $product = Product::forSeller(auth()->id())->whereKey($product->id)->lockForUpdate()->firstOrFail();
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
        $rows = $this->variantRows($request, (int) $product->stock, $product);
        if ($rows === null) return;
        $kept = [];
        foreach ($rows as $row) {
            $id = $row['id'];
            unset($row['id']);
            $variant = $id ? $product->variants()->findOrFail($id)
                : $product->variants()->where('variant_type', $row['variant_type'])->where('variant_value', $row['variant_value'])->first();
            if ($variant) {
                $variant->update($row);
            } else {
                $variant = $product->variants()->create($row);
            }
            $kept[] = $variant->id;
        }
        $product->variants()->whereNotIn('id', $kept)->delete();

        // Keep the product status in sync with variant stock.
        $totalVariantStock = $product->variants()->sum('stock');
        if ($totalVariantStock > 0 && $product->status === 'out_of_stock') {
            $product->update(['status' => 'active']);
        }
    }

    private function variantRows(Request $request, int $generalStock, ?Product $product = null): ?array
    {
        $request->validate([
            'variants' => ['nullable', 'array'],
            'variants.type' => ['sometimes', 'array', 'max:100'],
            'variants.type.*' => ['nullable', 'string', 'max:100'],
            'variants.value' => ['sometimes', 'array', 'max:100'],
            'variants.value.*' => ['nullable', 'string', 'max:100'],
            'variants.adjustment' => ['sometimes', 'array', 'max:100'],
            'variants.adjustment.*' => ['nullable', 'numeric', 'between:-1000000,1000000'],
            'variants.stock' => ['sometimes', 'array', 'max:100'],
            'variants.stock.*' => ['nullable', 'integer', 'between:0,99999'],
            'variants.id' => ['sometimes', 'array', 'max:100'],
            'variants.id.*' => ['nullable', 'integer', 'min:1', 'distinct'],
        ]);
        if (! $request->has('variants') && ! $request->boolean('variants_present')) {
            if ($product && (int) $product->variants()->sum('stock') > $generalStock) {
                throw \Illuminate\Validation\ValidationException::withMessages(['stock' => 'General stock cannot be less than the total stock allocated to variations.']);
            }
            return null;
        }
        $types = $request->input('variants.type', []);
        $values = $request->input('variants.value', []);
        $stocks = $request->input('variants.stock', []);
        $adjustments = $request->input('variants.adjustment', []);
        $ids = $request->input('variants.id', []);
        $rows = [];
        $labels = [];
        foreach (array_unique(array_merge(array_keys($types), array_keys($values), array_keys($stocks), array_keys($adjustments), array_keys($ids))) as $index) {
            $type = trim((string) ($types[$index] ?? ''));
            $value = trim((string) ($values[$index] ?? ''));
            $stock = (int) ($stocks[$index] ?? 0);
            if ($type === '' && $value === '' && $stock === 0 && empty($ids[$index])) continue;
            if ($type === '' || $value === '') {
                throw \Illuminate\Validation\ValidationException::withMessages(['variants' => 'Each variation needs both a type and a value.']);
            }
            $label = mb_strtolower($type.'\0'.$value);
            if (isset($labels[$label])) throw \Illuminate\Validation\ValidationException::withMessages(['variants' => 'Each variation must have a unique type and value.']);
            $labels[$label] = true;
            $id = ! empty($ids[$index]) ? (int) $ids[$index] : null;
            if ($id && (! $product || ! $product->variants()->whereKey($id)->exists())) {
                throw \Illuminate\Validation\ValidationException::withMessages(['variants' => 'A variation does not belong to this product. Reload the product and try again.']);
            }
            $rows[] = ['id' => $id, 'variant_type' => $type, 'variant_value' => $value,
                'price_adjustment' => (float) ($adjustments[$index] ?? 0), 'stock' => $stock, 'status' => 'active'];
        }
        if (array_sum(array_column($rows, 'stock')) > $generalStock) {
            throw \Illuminate\Validation\ValidationException::withMessages(['variants' => 'Total variation stock cannot exceed the product\'s general stock.']);
        }
        return $rows;
    }
}

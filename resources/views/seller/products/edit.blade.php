@extends('layouts.seller')

@section('title', 'Edit Product')

@section('content')
<div class="max-w-3xl">
    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('seller.products.index') }}" class="mb-2 inline-flex items-center gap-1 text-sm font-semibold text-ink-light hover:text-primary">
                <x-icon name="arrow-left" class="h-4 w-4" /> Back to inventory
            </a>
            <h2 class="text-xl font-extrabold tracking-tight">Edit Product</h2>
            <p class="text-sm text-ink-light">#{{ $product->id }} · {{ $product->name }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('seller.products.update', $product->id) }}" enctype="multipart/form-data"
          x-data="productForm()" class="mt-6 space-y-6">
        @csrf

        <div class="card p-6">
            <h3 class="mb-4 text-base font-bold">Basic Information</h3>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="field-label">Product Name</label>
                    <input type="text" name="name" value="{{ old('name', $product->name) }}" class="input" required>
                </div>
                <div>
                    <label class="field-label">Category</label>
                    <select name="category_id" class="select" required>
                        <option value="">Select category...</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ (old('category_id', $product->category_id) == $category->id) ? 'selected' : '' }}>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="field-label">Status</label>
                    <select name="status" class="select">
                        <option value="active" {{ old('status', $product->status) === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="out_of_stock" {{ old('status', $product->status) === 'out_of_stock' ? 'selected' : '' }}>Out of stock</option>
                        <option value="inactive" {{ old('status', $product->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="field-label">Description</label>
                    <textarea name="description" rows="4" class="textarea">{{ old('description', $product->description) }}</textarea>
                </div>
            </div>
        </div>

        <div class="card p-6">
            <h3 class="mb-4 text-base font-bold">Pricing &amp; Stock</h3>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div>
                    <label class="field-label">Selling Price (₱)</label>
                    <input type="number" name="price" step="0.01" min="0" value="{{ old('price', $product->price) }}" class="input" required>
                </div>
                <div>
                    <label class="field-label">Cost Price (₱)</label>
                    <input type="number" name="cost_price" step="0.01" min="0" value="{{ old('cost_price', $product->cost_price) }}" class="input">
                </div>
                <div>
                    <label class="field-label">Stock Quantity</label>
                    <input type="number" name="stock" min="0" value="{{ old('stock', $product->stock) }}" class="input" required>
                </div>
            </div>
        </div>

        <div class="card p-6">
            <h3 class="mb-4 text-base font-bold">Shipping Details</h3>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="field-label">Weight</label>
                    <input type="text" name="weight" maxlength="50" value="{{ old('weight', $product->weight) }}" class="input" placeholder="e.g. 1.5 kg">
                </div>
                <div>
                    <label class="field-label">Dimensions</label>
                    <input type="text" name="dimensions" maxlength="100" value="{{ old('dimensions', $product->dimensions) }}" class="input" placeholder="e.g. 30 × 20 × 15 cm">
                </div>
            </div>
            <p class="mt-3 text-xs text-ink-light">
                Parcel weight and size help riders estimate the shipping fee.
            </p>
        </div>

        <div class="card p-6">
            <div class="mb-1 flex items-center justify-between">
                <h3 class="text-base font-bold">Variations</h3>
                <button type="button" @click="addVariant()" class="btn btn-outline btn-sm">
                    <x-icon name="plus" class="h-4 w-4" /> Add Variation
                </button>
            </div>
            <p class="mb-4 text-xs text-ink-light">Add as many rows as you need — one row per value (e.g. Red, Blue, Green). <b>Price +₱</b> is added to the base price for that variation (use a negative number for a discount). <b>Stock</b> is how many pieces of that variation you have.</p>
            <div class="space-y-3">
                <div class="hidden grid-cols-5 items-center gap-3 px-3 text-[11px] font-extrabold uppercase tracking-wider text-ink-light md:grid" x-show="variants.length > 0">
                    <span>Type</span><span>Value</span><span>Price +₱</span><span>Stock (pcs)</span><span></span>
                </div>
                <template x-for="(v, i) in variants" :key="i">
                    <div class="grid grid-cols-2 items-center gap-3 rounded-2xl bg-basebg p-3 md:grid-cols-5">
                        <input type="text" x-model="v.type" :name="`variants[type][${i}]`" class="input !py-2.5" placeholder="Type (Color)">
                        <input type="text" x-model="v.value" :name="`variants[value][${i}]`" class="input !py-2.5" placeholder="Value (Red)">
                        <input type="number" step="0.01" x-model="v.adjustment" :name="`variants[adjustment][${i}]`" class="input !py-2.5" placeholder="+0.00">
                        <input type="number" min="0" x-model="v.stock" :name="`variants[stock][${i}]`" class="input !py-2.5" placeholder="e.g. 50">
                        <button type="button" @click="variants.splice(i, 1)" class="btn btn-danger btn-sm justify-self-start">
                            <x-icon name="trash" class="h-4 w-4" />
                        </button>
                    </div>
                </template>
                <p x-show="variants.length === 0" class="text-sm text-ink-light">No variations. This product sells by base stock.</p>
            </div>
        </div>

        <div class="card p-6">
            <h3 class="mb-4 text-base font-bold">Product Image</h3>
            <div x-data="imagePicker()" class="flex items-start gap-4">
                <div class="flex h-32 w-32 items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed border-borderline bg-basebg">
                    <template x-if="preview">
                        <img :src="preview" class="h-full w-full object-cover">
                    </template>
                    <template x-if="!preview && !hasExisting">
                        <x-icon name="box" class="h-8 w-8 text-ink-light" />
                    </template>
                    <template x-if="!preview && hasExisting">
                        <img src="{{ asset('storage/'.$product->image) }}" class="h-full w-full object-cover">
                    </template>
                </div>
                <div class="flex-1">
                    <label class="field-label">Replace image (optional)</label>
                    <input type="file" name="image" accept="image/*" class="input !py-2.5"
                           @change="preview = URL.createObjectURL($event.target.files[0])">
                    @if($product->image)
                        <p class="mt-2 text-xs text-ink-light">Current: {{ basename($product->image) }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="btn btn-primary px-8">Save Changes</button>
            <a href="{{ route('seller.products.index') }}" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function productForm() {
    return {
        variants: {!! json_encode($product->variants->map(fn($v) => ['type' => $v->variant_type, 'value' => $v->variant_value, 'adjustment' => $v->price_adjustment, 'stock' => $v->stock])) !!},
        addVariant() {
            this.variants.push({ type: '', value: '', adjustment: 0, stock: 0 });
        }
    }
}
function imagePicker() {
    return {
        preview: null,
        hasExisting: {{ $product->image ? 'true' : 'false' }},
    }
}
</script>
@endpush
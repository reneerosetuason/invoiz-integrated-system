@extends('layouts.seller')

@section('title', 'Inventory')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4">
    <div>
        <h2 class="text-xl font-extrabold tracking-tight">Inventory</h2>
        <p class="mt-0.5 text-sm text-ink-light">Add, update, and monitor your products, prices, and stock.</p>
    </div>
    <a href="{{ route('seller.products.create') }}" class="btn btn-primary">
        <x-icon name="plus" class="h-4 w-4" /> Add Product
    </a>
</div>

{{-- Filters --}}
<div class="card mt-6 p-4">
    <form method="GET" action="{{ route('seller.products.index') }}" class="flex flex-wrap items-end gap-3">
        <div class="min-w-[220px] flex-1">
            <label class="field-label">Search</label>
            <div class="relative">
                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-ink-light"><x-icon name="search" class="h-4 w-4"/></span>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search products..." class="input !pl-10">
            </div>
        </div>
        <div class="w-48">
            <label class="field-label">Category</label>
            <select name="category_id" class="select">
                <option value="">All categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" {{ ($filters['category_id'] ?? '') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="w-40">
            <label class="field-label">Status</label>
            <select name="status" class="select">
                <option value="">All</option>
                <option value="active" {{ ($filters['status'] ?? '') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' }}>Archived</option>
                <option value="out_of_stock" {{ ($filters['status'] ?? '') === 'out_of_stock' ? 'selected' : '' }}>Out of stock</option>
            </select>
        </div>
        <label class="flex cursor-pointer items-center gap-2 pb-2 text-sm text-ink-light">
            <input type="checkbox" name="low_stock" value="1" {{ isset($filters['low_stock']) ? 'checked' : '' }} class="h-4 w-4" style="accent-color:#16697A;">
            Low stock only
        </label>
        <button type="submit" class="btn btn-soft">Apply</button>
        <a href="{{ route('seller.products.index') }}" class="btn btn-ghost">Clear</a>
    </form>
</div>

{{-- Products table --}}
<div class="card mt-4 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th class="text-right">Price</th>
                    <th class="text-right">Cost</th>
                    <th class="text-right">Margin</th>
                    <th class="text-center">Stock</th>
                    <th class="text-center">Variants</th>
                    <th class="text-center">Rating</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                    <tr class="{{ $product->stock <= 5 && $product->status !== 'inactive' ? '!bg-amber-50' : '' }}">
                        <td>
                            <div class="flex items-center gap-3">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-soft">
                                    @if($product->image)
                                        <img src="{{ asset('storage/'.$product->image) }}" alt="" class="h-full w-full object-cover">
                                    @else
                                        <x-icon name="box" class="h-5 w-5 text-ink-light" />
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <a href="{{ route('seller.products.edit', $product->id) }}" class="block truncate font-semibold hover:text-primary hover:underline">{{ $product->name }}</a>
                                    <div class="text-[11px] text-ink-light">#{{ $product->id }} · {{ $product->created_at->format('M j, Y') }}</div>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge bg-soft !text-ink-light">{{ $product->category->name }}</span></td>
                        <td class="text-right font-semibold">{{ peso($product->price) }}</td>
                        <td class="text-right text-ink-light">{{ $product->cost_price !== null ? peso($product->cost_price) : '—' }}</td>
                        <td class="text-right">
                            @if($product->profit_per_unit !== null && $product->price > 0)
                                <span class="font-semibold text-successc">{{ round($product->profit_per_unit / $product->price * 100) }}%</span>
                            @else
                                <span class="text-ink-light">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="font-bold {{ $product->stock <= 5 ? 'text-warnc' : 'text-successc' }}">{{ $product->stock }}</span>
                            @if($product->stock <= 5)<span class="ml-1 text-[10px] font-bold uppercase text-warnc">low</span>@endif
                        </td>
                        <td class="text-center text-ink-light">{{ $product->variants_count }}</td>
                        <td class="text-center">
                            @if($product->rating)
                                <span class="font-bold">{{ $product->rating }}</span> <span class="rating-stars text-xs">★</span>
                            @else
                                <span class="text-ink-light">—</span>
                            @endif
                        </td>
                        <td><span class="badge badge-{{ $product->status }}">{{ str_replace('_', ' ', $product->status) }}</span></td>
                        <td class="text-right">
                            <div class="flex justify-end gap-1.5">
                                <a href="{{ route('seller.products.edit', $product->id) }}" class="btn btn-outline btn-xs" title="Edit">
                                    <x-icon name="edit" class="h-3.5 w-3.5" />
                                </a>
                                @if($product->status === 'inactive')
                                    <form method="POST" action="{{ route('seller.products.restore', $product->id) }}">
                                        @csrf
                                        <button class="btn btn-soft btn-xs" title="Restore">
                                            <x-icon name="refresh" class="h-3.5 w-3.5" />
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('seller.products.archive', $product->id) }}"
                                          onsubmit="return confirm('Archive this product? It will be hidden from buyers.');">
                                        @csrf
                                        <button class="btn btn-danger btn-xs" title="Archive">
                                            <x-icon name="archive" class="h-3.5 w-3.5" />
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="py-16 text-center">
                            <x-icon name="box" class="mx-auto h-10 w-10 text-ink-light" />
                            <p class="mt-3 font-semibold">No products found</p>
                            <a href="{{ route('seller.products.create') }}" class="mt-2 inline-block text-sm font-bold text-primary hover:underline">Add your first product</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
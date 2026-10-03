@extends('layouts.seller')

@section('title', 'Customer Feedback')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4">
    <div>
        <h2 class="text-xl font-extrabold tracking-tight">Customer Feedback</h2>
        <p class="mt-0.5 text-sm text-ink-light">Ratings and reviews left by buyers on your products.</p>
    </div>
</div>

{{-- Summary --}}
<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="card flex items-center gap-5 p-6">
        <div class="text-center">
            <div class="text-5xl font-extrabold">{{ number_format($stats['avg'], 1) }}</div>
            <div class="text-sm text-ink-light">average</div>
        </div>
        <div>
            <div class="text-lg">{!! rating_stars($stats['avg']) !!}</div>
            <div class="mt-1 text-sm text-ink-light">{{ $stats['total'] }} review(s)</div>
        </div>
    </div>
    <div class="card p-6 lg:col-span-2">
        <h3 class="mb-3 text-base font-bold">Rating Breakdown</h3>
        @foreach(['five' => 5, 'four' => 4, 'three' => 3, 'two' => 2, 'one' => 1] as $key => $stars)
            @php $pct = $stats['total'] > 0 ? round($stats[$key] / $stats['total'] * 100) : 0; @endphp
            <div class="mb-1.5 flex items-center gap-3 text-sm">
                <span class="w-8 font-semibold">{{ $stars }} ★</span>
                <div class="h-2 flex-1 overflow-hidden rounded-full" style="background:#F0EEE9;">
                    <div class="h-full rounded-full" style="background:#F5A623;width:{{ $pct }}%"></div>
                </div>
                <span class="w-10 text-right text-xs text-ink-light">{{ $stats[$key] }}</span>
            </div>
        @endforeach
    </div>
</div>

{{-- Filters --}}
<div class="card mt-6 p-4">
    <form method="GET" action="{{ route('seller.feedback.index') }}" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="field-label">Rating</label>
            <select name="rating" class="select w-40">
                <option value="">All ratings</option>
                @foreach([5,4,3,2,1] as $r)
                    <option value="{{ $r }}" {{ ($filters['rating'] ?? '') == $r ? 'selected' : '' }}>{{ $r }} star(s)</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="field-label">Status</label>
            <select name="status" class="select w-40">
                <option value="">All</option>
                <option value="visible" {{ ($filters['status'] ?? '') === 'visible' ? 'selected' : '' }}>Visible</option>
                <option value="hidden" {{ ($filters['status'] ?? '') === 'hidden' ? 'selected' : '' }}>Hidden</option>
            </select>
        </div>
        <button type="submit" class="btn btn-soft">Filter</button>
        <a href="{{ route('seller.feedback.index') }}" class="btn btn-ghost">Clear</a>
    </form>
</div>

{{-- Reviews list --}}
<div class="mt-4 space-y-4">
    @forelse($reviews as $review)
        <div class="card p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-start gap-4">
                    <div class="avatar h-11 w-11">{{ strtoupper(substr($review->buyer->first_name,0,1).substr($review->buyer->last_name,0,1)) }}</div>
                    <div>
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="font-bold">{{ $review->buyer->full_name }}</span>
                            <span class="text-lg">{!! rating_stars($review->rating) !!}</span>
                        </div>
                        <div class="mt-0.5 text-xs text-ink-light">{{ $review->created_at->format('M j, Y · g:i A') }}</div>
                        @if($review->comment)
                            <p class="mt-3 max-w-2xl text-sm leading-relaxed">"{{ $review->comment }}"</p>
                        @else
                            <p class="mt-3 text-sm italic text-ink-light">No written comment.</p>
                        @endif
                        <div class="mt-2 text-xs text-ink-light">
                            On <a href="{{ route('seller.products.edit', $review->product_id) }}" class="font-semibold text-primary hover:underline">{{ $review->product->name }}</a>
                            · Order <a href="{{ route('seller.orders.show', $review->order_id) }}" class="font-semibold text-primary hover:underline">#{{ $review->order_id }}</a>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="badge badge-{{ $review->status }}">{{ ucfirst($review->status) }}</span>
                    <form method="POST" action="{{ route('seller.feedback.toggle', $review->id) }}">
                        @csrf
                        <button type="submit" class="btn {{ $review->status === 'visible' ? 'btn-outline' : 'btn-soft' }} btn-sm">
                            @if($review->status === 'visible')
                                <x-icon name="eye-off" class="h-4 w-4" /> Hide
                            @else
                                <x-icon name="eye" class="h-4 w-4" /> Show
                            @endif
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div class="card p-16 text-center">
            <x-icon name="star" class="mx-auto h-10 w-10 text-ink-light" />
            <p class="mt-3 font-semibold">No feedback found</p>
            <p class="text-sm text-ink-light">Buyers' ratings will appear here after they receive their orders.</p>
        </div>
    @endforelse
</div>
@endsection
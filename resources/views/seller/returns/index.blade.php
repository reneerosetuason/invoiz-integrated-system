@extends('seller.layouts.app')

@section('title', 'Returns & Refunds')

@section('content')
<div class="flex flex-wrap items-start justify-between gap-3">
    <div>
        <h2 class="text-xl font-extrabold tracking-tight">Returns &amp; Refunds</h2>
        <p class="mt-0.5 text-sm text-ink-light">Buyers can request a return within {{ $windowDays }} days of delivery. Confirm receipt only after the item comes back. Refund payments must be arranged with the buyer.</p>
    </div>
    <a href="{{ route('seller.returns.index') }}" class="btn btn-outline" data-return-refresh>Refresh requests</a>
</div>
<p class="mt-2 text-xs text-ink-light" role="status" data-return-refresh-status>Requests refresh automatically while this page is open.</p>

@if(session('success'))
    <div class="card mt-4 border-successc bg-[#E8F5EE] px-4 py-3 text-sm font-semibold text-successc">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="card mt-4 border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ session('error') }}</div>
@endif

<div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mt-4">
    <div class="card px-4 py-3">
        <div class="text-xs font-bold uppercase tracking-wide text-ink-light">All requests</div>
        <div class="mt-1 text-2xl font-extrabold" data-return-count="all">{{ $counts['all'] }}</div>
    </div>
    <div class="card px-4 py-3">
        <div class="text-xs font-bold uppercase tracking-wide text-ink-light">Awaiting decision</div>
        <div class="mt-1 text-2xl font-extrabold text-amber-700" data-return-count="requested">{{ $counts['requested'] }}</div>
    </div>
    <div class="card px-4 py-3">
        <div class="text-xs font-bold uppercase tracking-wide text-ink-light">Awaiting item</div>
        <div class="mt-1 text-2xl font-extrabold" data-return-count="approved">{{ $counts['approved'] }}</div>
    </div>
    <div class="card px-4 py-3">
        <div class="text-xs font-bold uppercase tracking-wide text-ink-light">Refund recorded</div>
        <div class="mt-1 text-2xl font-extrabold text-successc" data-return-count="refunded">{{ $counts['refunded'] }}</div>
    </div>
</div>

<div data-return-records data-version="{{ $version }}">
    @include('seller.returns._records')
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/seller/js/returns/index.js') }}?v={{ filemtime(public_path('assets/seller/js/returns/index.js')) }}" defer></script>
@endpush

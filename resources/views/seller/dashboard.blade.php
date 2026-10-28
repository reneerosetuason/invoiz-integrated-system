@extends('seller.layouts.app')

@section('title', 'Dashboard')

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,560;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"> <!-- taste-skill-ignore: matches the existing Invoiz admin type pairing -->
<link rel="stylesheet" href="{{ asset('assets/seller/css/dashboard.css') }}">
@endpush

@section('content')
@php
    $sellerStoreName = auth()->user()->seller?->business_name ?: 'Your store';
@endphp
<div class="seller-dashboard">
    <header class="dash-head">
        <div>
            <h1>Store overview</h1>
            <p>{{ $sellerStoreName }} · Orders, settled sales and inventory</p>
        </div>
        <a class="dash-primary" href="{{ route('seller.orders.index') }}">
            <span>{{ ($totals['pending'] ?? 0) > 0 ? 'Review orders' : 'View orders' }}</span>
            @if(($totals['pending'] ?? 0) > 0)<span>({{ $totals['pending'] }})</span>@endif
            <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M3.5 10h12M10 4.5l5.5 5.5-5.5 5.5"/></svg>
        </a>
    </header>

    <section class="dash-overview" aria-label="Settled sales and items needing attention">
        <div class="dash-revenue">
            <h2>Settled sales</h2>
            <strong class="dash-sales-value">{{ peso($totals['revenue']) }}</strong>
            <p class="dash-sales-caption">
                Today: <strong>{{ peso($totals['revenue_today']) }}</strong>
                @if(($totals['remittance_pending'] ?? 0) > 0)
                    <span aria-hidden="true"> · </span><a href="{{ route('seller.remittances.index') }}">Cash to confirm: {{ peso($totals['remittance_amount'] ?? 0) }}</a>
                @else
                    <span aria-hidden="true"> · </span><a href="{{ route('seller.remittances.index') }}">Cash settled</a>
                @endif
                <span aria-hidden="true"> · </span>Confirmed: {{ peso($totals['remittance_confirmed'] ?? 0) }}
            </p>
        </div>
        <div class="dash-queue">
            <h2>Needs attention</h2>
            <div class="dash-queue-list">
                <a class="dash-queue-link" href="{{ route('seller.orders.index') }}">
                    <span>New or pending</span><strong>{{ $totals['pending'] }}</strong>
                </a>
                <a class="dash-queue-link" href="{{ route('seller.orders.index') }}">
                    <span>Ready to ship</span><strong>{{ $totals['to_ship'] }}</strong>
                </a>
                <a class="dash-queue-link" href="{{ route('seller.products.index') }}">
                    <span>Low stock</span><strong>{{ $totals['low_stock'] }}</strong>
                </a>
            </div>
        </div>
    </section>

    <section class="dash-metrics" aria-label="Store performance">
        <div class="dash-metric"><span>Orders placed</span><strong>{{ $totals['orders'] }}</strong><small>All time</small></div>
        <div class="dash-metric"><span>Delivered</span><strong>{{ $totals['delivered'] }}</strong><small>Orders completed</small></div>
        <div class="dash-metric"><span>Products listed</span><strong>{{ $totals['products'] }}</strong><small>{{ $totals['low_stock'] }} need restocking</small></div>
        <div class="dash-metric"><span>Average rating</span><strong>{{ number_format((float) $totals['rating'], 1) }} / 5</strong><small>Across rated products</small></div>
    </section>

    <div class="dash-analysis">
        <section class="dash-panel" aria-labelledby="dash-sales-title">
            <h2 id="dash-sales-title">Sales over the last 30 days</h2>
            <p class="dash-subtitle">Settled sales from delivered orders</p>
            <div class="dash-sales-chart">
                <canvas id="salesChart" role="img" aria-label="Line chart of settled sales over the last 30 days"></canvas>
            </div>
        </section>
        <section class="dash-panel" aria-labelledby="dash-status-title">
            <h2 id="dash-status-title">Order status</h2>
            <p class="dash-subtitle">Your current order mix</p>
            <div class="dash-status-layout">
                <div class="dash-status-chart">
                    <canvas id="statusChart" role="img" aria-label="Doughnut chart showing orders by status"></canvas>
                </div>
                <div class="dash-status-list">
                    @php
                        $orderTotal = array_sum($statusCounts);
                    @endphp
                    @forelse($statusCounts as $status => $count)
                        @php
                            $statusTone = in_array($status, ['pending', 'confirmed'], true)
                                ? ''
                                : (in_array($status, ['processing', 'ready_for_delivery', 'out_for_delivery', 'picked_up'], true)
                                    ? 'dash-status-dot--mid'
                                    : (in_array($status, ['delivered', 'completed'], true) ? 'dash-status-dot--soft' : 'dash-status-dot--muted'));
                        @endphp
                        <div class="dash-status-row">
                            <span class="dash-status-name"><span class="dash-status-dot {{ $statusTone }}" aria-hidden="true"></span>{{ order_status_label($status) }}</span>
                            <strong class="dash-status-count">{{ $count }}</strong>
                            <span class="dash-status-share">{{ $orderTotal > 0 ? round($count / $orderTotal * 100) : 0 }}%</span>
                        </div>
                    @empty
                        <p class="dash-empty">No orders yet.</p>
                    @endforelse
                </div>
            </div>
        </section>
    </div>

    <div class="dash-bottom">
        <section class="dash-panel dash-orders-panel" aria-labelledby="dash-orders-title">
            <div class="dash-panel-head">
                <div><h2 id="dash-orders-title">Recent orders</h2><p class="dash-subtitle">Your latest customer activity</p></div>
                <a href="{{ route('seller.orders.index') }}" class="dash-section-link">All orders</a>
            </div>
            <div class="dash-order-wrap">
                <table class="dash-order-table" aria-label="Recent seller orders">
                    <thead><tr><th scope="col">Order</th><th scope="col">Buyer</th><th scope="col">Total</th><th scope="col">Status</th><th scope="col">Date</th></tr></thead>
                    <tbody>
                        @forelse($recentOrders as $order)
                            <tr>
                                <td class="dash-order-cell"><a href="{{ route('seller.orders.show', $order->id) }}" class="dash-order-id">#{{ $order->id }}</a></td>
                                <td class="dash-buyer-cell">{{ $order->buyer?->full_name ?? 'Buyer' }}</td>
                                <td class="dash-order-total-cell"><span class="dash-order-total">{{ peso($order->seller_subtotal ?? $order->total_amount) }}</span></td>
                                <td class="dash-order-status-cell"><span class="dash-order-status">{{ order_status_label($order->status) }}</span></td>
                                <td class="dash-order-date-cell"><time class="dash-order-date" datetime="{{ $order->created_at->toIso8601String() }}">{{ $order->created_at->format('M j, g:i A') }}</time></td>
                            </tr>
                        @empty
                            <tr class="dash-order-empty"><td colspan="5">No orders yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="dash-panel dash-top-products" aria-labelledby="dash-products-title">
            <div class="dash-panel-head">
                <div><h2 id="dash-products-title">Top products</h2><p class="dash-subtitle">By units sold</p></div>
                <a href="{{ route('seller.products.index') }}" class="dash-section-link">Inventory</a>
            </div>
            <div class="dash-product-list">
                @forelse($topProducts as $product)
                    @php
                        $topUnits = $topProducts->max('units') ?: 0;
                    @endphp
                    <div class="dash-product-row">
                        <div class="dash-product-copy">
                            <span class="dash-product-name" title="{{ $product->product_name }}">{{ $product->product_name }}</span>
                            <p class="dash-product-meta">{{ $product->units }} units · {{ peso($product->sales) }}</p>
                        </div>
                        <div class="dash-product-track" aria-hidden="true"><span style="width:{{ $topUnits ? round($product->units / $topUnits * 100) : 0 }}%"></span></div>
                    </div>
                @empty
                    <p class="dash-empty">No settled product sales yet.</p>
                @endforelse
            </div>
        </section>
    </div>

    <section class="dash-panel dash-interest" aria-labelledby="dash-interest-title">
        <div class="dash-panel-head">
            <div><h2 id="dash-interest-title">What shoppers want</h2><p class="dash-subtitle">Use product interest to guide your next restock.</p></div>
        </div>
        <div class="dash-interest-grid">
            <div class="dash-interest-group">
                <h3>Most viewed</h3>
                <div class="dash-product-list">
                    @forelse($mostViewed->where('views_count', '>', 0) as $product)
                        @php
                            $viewMax = $mostViewed->max('views_count') ?: 0;
                        @endphp
                        <div class="dash-product-row">
                            <div class="dash-product-copy">
                                <span class="dash-product-name" title="{{ $product->name }}">{{ $product->name }}</span>
                                <p class="dash-product-meta">{{ number_format($product->views_count) }} views</p>
                            </div>
                            <div class="dash-product-track" aria-hidden="true"><span style="width:{{ $viewMax ? round($product->views_count / $viewMax * 100) : 0 }}%"></span></div>
                        </div>
                    @empty
                        <p class="dash-empty">No product views yet.</p>
                    @endforelse
                </div>
            </div>
            <div class="dash-interest-group">
                <h3>Most added to cart</h3>
                <div class="dash-product-list">
                    @forelse($mostAdded->where('cart_additions', '>', 0) as $product)
                        @php
                            $cartMax = $mostAdded->max('cart_additions') ?: 0;
                        @endphp
                        <div class="dash-product-row">
                            <div class="dash-product-copy">
                                <span class="dash-product-name" title="{{ $product->name }}">{{ $product->name }}</span>
                                <p class="dash-product-meta">{{ number_format($product->cart_additions) }} cart additions</p>
                            </div>
                            <div class="dash-product-track" aria-hidden="true"><span style="width:{{ $cartMax ? round($product->cart_additions / $cartMax * 100) : 0 }}%"></span></div>
                        </div>
                    @empty
                        <p class="dash-empty">No cart additions yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
@push('scripts')
<script src="{{ asset('assets/seller/js/dashboard.js') }}" data-config="{{ json_encode([(json_decode(json_encode($salesSeries['labels']), true)), (json_decode(json_encode($salesSeries['values']), true)), (json_decode(json_encode(array_keys($statusCounts)), true)), (json_decode(json_encode(array_map('order_status_label', array_keys($statusCounts))), true)), (json_decode(json_encode(array_values($statusCounts)), true))], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) }}"></script>
@endpush

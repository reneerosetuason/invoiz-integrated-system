@extends('layouts.website')
@section('content')
@php
  $o = $order;
  $pay = $o->payment;
  $del = $o->delivery;
  $steps = [
    ['key' => 'pending', 'label' => 'Placed'],
    ['key' => 'confirmed', 'label' => 'Confirmed'],
    ['key' => 'out_for_delivery', 'label' => 'On the way'],
    ['key' => 'delivered', 'label' => 'Delivered'],
  ];
  // Fold intermediate seller stages into "Confirmed".
  $folded = in_array($o->status, ['processing','ready_for_delivery']) ? 'confirmed' : $o->status;
  $idx = collect($steps)->search(fn($s) => $s['key'] === $folded);
  $idx = $idx === false ? -1 : $idx;
  $statusColor = [
    'pending' => '#C77F02', 'confirmed' => '#1D4ED8', 'processing' => '#1D4ED8',
    'ready_for_delivery' => '#1D4ED8', 'out_for_delivery' => '#7C3AED',
    'delivered' => '#10b981', 'cancelled' => '#ef4444',
  ][$o->status] ?? '#1a1a1a';
@endphp
@php
  $oRef = $o->order_number ?? ('INV-'.$o->id);
  $oStore = '';
  try { $oStore = \App\Models\Seller::nameFor(optional($o->items->first())->seller_id ?? 0); } catch (\Throwable $e) {}
@endphp
<a href="{{ url('/orders') }}" style="color:var(--text2);text-decoration:none;font-weight:600;font-size:12px">← Back to orders</a>
<h2 style="font-weight:800;margin:8px 0 0">{{ $oRef }}</h2>
@if($oStore && $oStore !== 'Store')<div style="font-size:12px;color:var(--text2);margin-top:2px">Sold by <a href="{{ url('/store/'.optional($o->items->first())->seller_id) }}" style="color:var(--primary-dark);font-weight:700;text-decoration:none">{{ $oStore }}</a></div>@endif

@if(session('success'))<div style="background:#ecfdf5;color:#065f46;padding:10px;border-radius:8px;border:1px solid #a7f3d0;margin-top:12px;font-size:13px;font-weight:600">{{ session('success') }}</div>@endif
@if(session('error'))<div style="background:#fef2f2;color:#991b1b;padding:10px;border-radius:8px;border:1px solid #fecaca;margin-top:12px;font-size:13px;font-weight:600">{{ session('error') }}</div>@endif

<div style="background:#fff;border:1px solid var(--border);border-radius:10px;padding:14px;margin-top:12px">
  <div style="font-size:14px">Status: <b style="color:{{ $statusColor }};text-transform:uppercase">{{ str_replace('_',' ',$o->status) }}</b></div>
  <div style="font-size:12px;color:var(--text2);margin-top:6px">Placed on: {{ $o->created_at->format('Y-m-d H:i') }}</div>
  @if($pay)<div style="font-size:12px;color:var(--text2);margin-top:2px">Payment: {{ strtoupper(str_replace('_',' ',$pay->method)) }} · {{ strtoupper($pay->status) }}</div>@endif
  @if($del)<div style="font-size:12px;color:var(--text2);margin-top:2px">Delivery: {{ strtoupper(str_replace('_',' ',$del->status)) }}</div>@endif
</div>

@if($o->status === 'cancelled')
  <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px;margin-top:12px;color:#991b1b;font-weight:700;font-size:13px">This order was cancelled.</div>
@else
  <div style="background:#fff;border:1px solid var(--border);border-radius:10px;padding:16px;margin-top:12px">
    <div style="font-weight:800;font-size:14px;margin-bottom:14px">Order Progress</div>
    <div style="display:flex;align-items:flex-start">
      @foreach($steps as $i => $s)
        @php $done = $i < $idx || $o->status === 'delivered'; $current = $i === $idx && $o->status !== 'delivered'; @endphp
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px">
          <div style="width:26px;height:26px;border-radius:50%;display:grid;place-items:center;font-size:12px;font-weight:800;
            {{ $done ? 'background:var(--green);color:#fff;' : ($current ? 'background:#fff;border:2px solid var(--green);color:var(--green);' : 'background:#e5e5e5;color:#fff;') }}">
            @if($done)<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>@else<span style="width:8px;height:8px;border-radius:50%;background:currentColor;display:block"></span>@endif
          </div>
          <div style="font-size:11px;font-weight:{{ $done || $current ? '700' : '400' }};color:{{ $done || $current ? 'var(--green)' : 'var(--text3)' }}">{{ $s['label'] }}</div>
        </div>
        @if(!$loop->last)
          <div style="flex:1;height:3px;border-radius:2px;margin-top:12px;background:{{ $i < $idx || $o->status === 'delivered' ? 'var(--green)' : '#e5e5e5' }}"></div>
        @endif
      @endforeach
    </div>
  </div>
@endif

<div style="background:#fff;border:1px solid var(--border);border-radius:10px;padding:16px;margin-top:12px">
  <div style="font-weight:800;font-size:14px;margin-bottom:8px">Items</div>
  @foreach($o->items as $it)
    <div style="padding:8px 0;border-bottom:1px solid var(--border-light);font-size:13px">
      <div style="display:flex;justify-content:space-between;gap:10px">
        <b>{{ $it->product_name }}</b>
        <span style="white-space:nowrap">{{ $it->quantity }} × ₱{{ number_format($it->price,2) }}</span>
      </div>
      @if($it->variant_label)<div style="font-size:11px;color:var(--text3);margin-top:2px">{{ $it->variant_label }}</div>@endif
      <div style="font-size:12px;color:var(--text2);margin-top:2px">Subtotal: ₱{{ number_format($it->subtotal ?? $it->price * $it->quantity,2) }}</div>
    </div>
  @endforeach
  <div style="display:flex;justify-content:space-between;margin-top:10px;font-size:13px;color:var(--text2)">
    <span>Subtotal</span><span>₱{{ number_format($o->total_amount,2) }}</span>
  </div>
  <div style="display:flex;justify-content:space-between;margin-top:6px;font-weight:800;font-size:15px">
    <span>Total</span><span style="color:var(--green)">₱{{ number_format($o->total_amount,2) }}</span>
  </div>
</div>

<div style="background:#fff;border:1px solid var(--border);border-radius:10px;padding:16px;margin-top:12px">
  <div style="font-weight:800;font-size:14px;margin-bottom:10px">Order Timeline</div>
  @forelse($o->statusHistories->sortByDesc('id') as $h)
    <div style="display:flex;gap:10px;margin-bottom:12px">
      <div style="display:flex;flex-direction:column;align-items:center">
        <span style="width:10px;height:10px;border-radius:50%;background:var(--primary,#16697A);flex-shrink:0;margin-top:4px"></span>
        @if(!$loop->last)<span style="width:2px;flex:1;background:var(--border-light);min-height:14px"></span>@endif
      </div>
      <div>
        <div style="font-weight:800;font-size:13px;text-transform:uppercase">{{ str_replace('_',' ',$h->to_status) }}</div>
        @if($h->note)<div style="font-size:12px;color:var(--text2)">{{ $h->note }}</div>@endif
        <div style="font-size:11px;color:var(--text3)">{{ $h->created_at->format('Y-m-d H:i') }}</div>
      </div>
    </div>
  @empty
    <div style="font-size:12px;color:var(--text3)">No status updates yet.</div>
  @endforelse
</div>

@if($o->isCancellableByBuyer())
  <form method="POST" action="{{ url('/orders/'.$o->id.'/cancel') }}" style="margin-top:12px" onsubmit="return confirm('Cancel this order?');">
    @csrf
    <button type="submit" style="width:100%;padding:11px;border-radius:999px;background:#fff;color:var(--danger);border:1.5px solid var(--danger);font-weight:700;cursor:pointer;font-size:13px;font-family:inherit">Cancel Order</button>
  </form>
@elseif(! in_array($o->status, ['cancelled','delivered']))
  <div style="margin-top:12px;padding:11px;border-radius:10px;background:#f3f4f6;color:var(--text2);font-size:12px;text-align:center">This order is {{ str_replace('_',' ',$o->status) }} and can no longer be cancelled. Contact the seller if you need help.</div>
@endif
@endsection

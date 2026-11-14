@extends('buyer.layouts.website')
@section('content')
<p style="margin:12px 0"><a href="{{ route('returns.center') }}">Track returns &amp; confirm refund payment</a></p>
@php
  $o = $order;
  $pay = $o->payment;
  $del = $o->deliveries->first();
  $steps = [
    ['key' => 'placed', 'label' => 'Placed'],
    ['key' => 'confirmed', 'label' => 'Confirmed'],
    ['key' => 'in_transit', 'label' => 'On the way'],
    ['key' => 'delivered', 'label' => 'Delivered'],
  ];
  $progressStage = \App\Support\CustomerTracking::progressStage($o, $del);
  $idx = collect($steps)->search(fn($s) => $s['key'] === $progressStage);
  $idx = $idx === false ? -1 : $idx;
  $customerOrderLabel = \App\Support\CustomerTracking::orderLabel($o);
  $customerDeliveryLabel = \App\Support\CustomerTracking::label($o, $del, \App\Support\CustomerTracking::AUDIENCE_BUYER);
  $hubVerified = $del ? \App\Support\CustomerTracking::verifiedCustody($del) : false;
  $statusColor = [
    'pending' => '#C77F02', 'confirmed' => '#1D4ED8', 'processing' => '#1D4ED8',
    'ready_for_delivery' => '#1D4ED8', 'shipped' => '#7C3AED', 'out_for_delivery' => '#7C3AED',
    'delivered' => '#10b981', 'cancelled' => '#ef4444',
  ][$o->status] ?? '#1a1a1a';
  $isDelivered = $o->status === 'delivered'
    || optional($del)->status === 'delivered'
    || $progressStage === 'delivered';
@endphp
@foreach($o->deliveries as $chatParcel)
    @include('shared.messages.parcel-contact', ['parcel' => $chatParcel])
@endforeach
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
  <div style="font-size:14px">Status: <b style="color:{{ $statusColor }};text-transform:uppercase">{{ $customerOrderLabel }}</b></div>
  <div style="font-size:12px;color:var(--text2);margin-top:6px">Placed on: {{ $o->created_at->format('Y-m-d H:i') }}</div>
  @if($pay)<div style="font-size:12px;color:var(--text2);margin-top:2px">Payment: {{ strtoupper(str_replace('_',' ',$pay->method)) }} · {{ strtoupper($pay->status) }}</div>@endif
  @if($pay?->method === 'gcash' && $pay?->proof_path)
    <div style="margin-top:8px;font-size:12px"><a data-image-viewer="document" href="{{ route('buyer.orders.payment-proof', $o->id) }}"   style="color:var(--green);font-weight:700">View your GCash receipt</a></div>
  @endif
  @if($del)<div style="font-size:12px;color:var(--text2);margin-top:2px">Delivery: {{ strtoupper($customerDeliveryLabel) }}</div>@endif
</div>

@if($o->deliveries->isNotEmpty())
  <div style="background:#fff;border:1px solid var(--border);border-radius:10px;padding:14px;margin-top:12px">
    <div style="font-weight:800;font-size:14px">Shipment Tracking</div>
    <div style="font-size:11px;color:var(--text2);margin-top:4px">Use the tracking number when contacting the courier or logistics center.</div>
    @foreach($o->deliveries as $shipment)
      <div style="border-top:1px solid var(--border-light);margin-top:10px;padding-top:10px;font-size:12px">
        @if($o->deliveries->count() > 1)
          <div style="font-weight:700;margin-bottom:4px">Package {{ $loop->iteration }} of {{ $o->deliveries->count() }}</div>
        @endif
        <div>Status: <b style="text-transform:uppercase">{{ \App\Support\CustomerTracking::label($o, $shipment, \App\Support\CustomerTracking::AUDIENCE_BUYER) }}</b></div>
        @if($shipment->tracking_number)
          <div style="margin-top:3px">Tracking number: <b style="font-family:monospace;font-size:13px;letter-spacing:.03em">{{ $shipment->tracking_number }}</b></div>
        @else
          <div style="margin-top:3px;color:var(--text2)">Tracking number will appear once the parcel is booked.</div>
        @endif
      </div>
    @endforeach
  </div>
@endif

@if($del && $del->parcel_status && $hubVerified)
  @php
    $parcelSteps = [
      'pending_arrival' => ['label' => 'Pending arrival', 'index' => 0],
      'received' => ['label' => 'Received', 'index' => 1],
      'scanned' => ['label' => 'Scanned', 'index' => 2],
      'sorted' => ['label' => 'Sorted', 'index' => 3],
      'dispatched' => ['label' => 'Dispatched', 'index' => 4],
    ];
    $parcelStatus = $del->parcel_status;
    $parcelIndex = $parcelSteps[$parcelStatus]['index'] ?? 0;
    $parcelAtHub = in_array($parcelStatus, ['received', 'scanned', 'sorted'], true);
  @endphp
  <div style="background:#fff;border:1px solid var(--border);border-radius:10px;padding:14px;margin-top:12px">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
      <div style="font-weight:800;font-size:14px">Parcel Processing</div>
      <div style="display:flex;align-items:center;gap:6px">
        @if($parcelAtHub)
          <span style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;border-radius:999px;padding:2px 8px;font-size:10px;font-weight:800">AT HUB</span>
        @endif
        <span style="background:#f3f4f6;color:#4b5563;border:1px solid #e5e7eb;border-radius:999px;padding:2px 8px;font-size:10px;font-weight:800">{{ strtoupper(str_replace('_', ' ', $parcelStatus)) }}</span>
      </div>
    </div>
    <div style="font-size:11px;color:var(--text2);margin-top:4px">Updates from the logistics hub. This is separate from your order status.</div>
    <div style="display:flex;align-items:flex-start;margin-top:12px">
      @foreach($parcelSteps as $key => $step)
        @php $done = $parcelIndex > $step['index']; $current = $parcelIndex === $step['index']; @endphp
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:5px">
          <div style="width:22px;height:22px;border-radius:50%;display:grid;place-items:center;font-size:11px;font-weight:800;
            {{ $done ? 'background:var(--green);color:#fff;' : ($current ? 'background:#fff;border:2px solid var(--green);color:var(--green);' : 'background:#e5e5e5;color:#fff;') }}">
            @if($done)<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>@else<span style="width:6px;height:6px;border-radius:50%;background:currentColor;display:block"></span>@endif
          </div>
          <div style="font-size:10px;text-align:center;font-weight:{{ $done || $current ? '700' : '400' }};color:{{ $done || $current ? 'var(--green)' : 'var(--text3)' }}">{{ $step['label'] }}</div>
        </div>
        @if(!$loop->last)
          <div style="flex:1;height:3px;border-radius:2px;margin-top:11px;background:{{ $done ? 'var(--green)' : '#e5e5e5' }}"></div>
        @endif
      @endforeach
    </div>
  </div>
@elseif($del && ! $hubVerified && ! in_array($o->status, ['delivered', 'cancelled'], true))
  <div style="background:#fff;border:1px solid var(--border);border-radius:10px;padding:14px;margin-top:12px">
    <div style="font-weight:800;font-size:14px">Pickup Status</div>
    <div style="font-size:13px;font-weight:700;margin-top:4px">{{ $customerDeliveryLabel }}</div>
    <div style="font-size:11px;color:var(--text2);margin-top:4px">
      @if($del->picked_up_at)
        The parcel is with the pickup rider and has not reached the logistics hub yet.
      @else
        The parcel has not been collected by a rider yet. Hub processing has not started.
      @endif
    </div>
  </div>
@endif

@if($o->status === 'cancelled')
  <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px;margin-top:12px;color:#991b1b;font-weight:700;font-size:13px">This order was cancelled.@if($o->cancellation_reason)<div style="margin-top:5px;font-weight:500">Reason: {{ $o->cancellation_reason }}</div>@endif</div>
@else
  <div style="background:#fff;border:1px solid var(--border);border-radius:10px;padding:16px;margin-top:12px">
    <div style="font-weight:800;font-size:14px;margin-bottom:14px">Order Progress</div>
    <div style="display:flex;align-items:flex-start">
      @foreach($steps as $i => $s)
        @php $done = $i < $idx || $isDelivered; $current = $i === $idx && ! $isDelivered; @endphp
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px">
          <div style="width:26px;height:26px;border-radius:50%;display:grid;place-items:center;font-size:12px;font-weight:800;
            {{ $done ? 'background:var(--green);color:#fff;' : ($current ? 'background:#fff;border:2px solid var(--green);color:var(--green);' : 'background:#e5e5e5;color:#fff;') }}">
            @if($done)<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>@else<span style="width:8px;height:8px;border-radius:50%;background:currentColor;display:block"></span>@endif
          </div>
          <div style="font-size:11px;font-weight:{{ $done || $current ? '700' : '400' }};color:{{ $done || $current ? 'var(--green)' : 'var(--text3)' }}">{{ $s['label'] }}</div>
        </div>
        @if(!$loop->last)
          <div style="flex:1;height:3px;border-radius:2px;margin-top:12px;background:{{ $i < $idx || $isDelivered ? 'var(--green)' : '#e5e5e5' }}"></div>
        @endif
      @endforeach
    </div>
  </div>
@endif

@if($isDelivered)
  <div id="rate" style="background:#fff;border:1px solid var(--border);border-radius:10px;padding:16px;margin-top:12px;box-shadow:0 1px 3px rgba(0,0,0,0.03);">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid var(--border-light);">
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:36px;height:36px;border-radius:50%;background:#FEF3C7;display:flex;align-items:center;justify-content:center;color:#D97706;flex-shrink:0;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="#D97706" stroke="#D97706"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        </div>
        <div>
          <h3 style="margin:0;font-size:15px;font-weight:800;color:var(--text1,#111827);">Rate Your Items</h3>
          <p style="margin:2px 0 0;font-size:12px;color:var(--text2,#6B7280);">Your order has arrived! How was your experience? Leave a rating and review for your items.</p>
        </div>
      </div>
      @php
        $totalItems = $o->items->count();
        $ratedItemsCount = $o->items->filter(fn($it) => ($reviews ?? collect())->has($it->product_id))->count();
      @endphp
      <span style="font-size:11.5px;font-weight:700;padding:4px 10px;border-radius:999px;background:{{ $ratedItemsCount === $totalItems ? '#E8F5EE' : '#FEF3C7' }};color:{{ $ratedItemsCount === $totalItems ? '#166534' : '#92400E' }};">
        {{ $ratedItemsCount }}/{{ $totalItems }} Rated
      </span>
    </div>

    <div style="display:flex;flex-direction:column;gap:12px;">
      @foreach($o->items as $it)
        @php
          $prod = $it->product;
          $hasImg = $prod && $prod->image && file_exists(storage_path('app/public/'.$prod->image));
          $existingReview = ($reviews ?? collect())->get($it->product_id);
        @endphp
        <div style="display:flex;align-items:center;justify-content:space-between;gap:14px;padding:12px;border:1px solid var(--border-light,#F1F0EC);border-radius:10px;background:#FAFAF8;flex-wrap:wrap;">
          <div style="display:flex;align-items:center;gap:12px;min-width:200px;flex:1;">
            @if($hasImg)
              <img src="{{ asset('storage/'.$prod->image) }}" alt="{{ $it->product_name }}" style="width:48px;height:48px;border-radius:8px;object-fit:cover;border:1px solid #E5E7EB;">
            @else
              <div style="width:48px;height:48px;border-radius:8px;background:#E5E7EB;display:flex;align-items:center;justify-content:center;font-weight:800;color:#6B7280;font-size:14px;">
                {{ strtoupper(substr($it->product_name,0,2)) }}
              </div>
            @endif
            <div>
              <div style="font-weight:700;font-size:13.5px;color:var(--text1,#111827);">{{ $it->product_name }}</div>
              @if($it->variant_label)
                <div style="font-size:11.5px;color:var(--text2,#6B7280);margin-top:2px;">{{ $it->variant_label }}</div>
              @endif
              <div style="font-size:12px;color:var(--text3,#9CA3AF);margin-top:1px;">Qty {{ $it->quantity }} &bull; ₱{{ number_format($it->price, 2) }}</div>
            </div>
          </div>

          <div style="display:flex;align-items:center;gap:10px;">
            @if($existingReview)
              <div style="text-align:right;">
                <div style="display:inline-flex;align-items:center;gap:2px;">
                  @for($star=1;$star<=5;$star++)
                    @if($star <= (int)$existingReview->rating)
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="#D97706" stroke="#D97706"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    @else
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#D1D5DB" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    @endif
                  @endfor
                  <span style="font-size:12px;font-weight:700;color:#92400E;margin-left:4px;">{{ $existingReview->rating }}/5</span>
                </div>
                @if($existingReview->comment)
                  <div style="font-size:11.5px;color:#4B5563;margin-top:2px;max-width:240px;text-overflow:ellipsis;overflow:hidden;white-space:nowrap;">&ldquo;{{ $existingReview->comment }}&rdquo;</div>
                @endif
              </div>
              <button type="button" onclick="openRateModal({{ $it->product_id }}, '{{ addslashes($it->product_name) }}', {{ $existingReview->rating }}, '{{ addslashes($existingReview->comment ?? '') }}')" class="btn-p btn-line btn-sm" style="font-size:12px;padding:6px 12px;cursor:pointer;border-radius:8px;">
                Edit
              </button>
            @else
              <button type="button" onclick="openRateModal({{ $it->product_id }}, '{{ addslashes($it->product_name) }}', 5, '')" class="btn-p btn-solid btn-sm" style="background:#16697A;color:#fff;border-radius:8px;font-size:12.5px;font-weight:700;padding:6px 16px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="#FBBF24" stroke="#FBBF24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                Rate Now
              </button>
            @endif
          </div>
        </div>
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
      @if($isDelivered)
        @php
          $existingReview = ($reviews ?? collect())->get($it->product_id);
          $itemReturns = ($returns ?? collect())->get($it->id, collect());
          $openReturn = $itemReturns->first(fn($r) => in_array($r->status, ['requested', 'approved', 'item_received']));
          $refundedReturn = $itemReturns->first(fn($r) => $r->status === 'refunded');
          $refundedQty = (int) $itemReturns->where('status', 'refunded')->sum('quantity');
          $remainingQty = max(0, (int)$it->quantity - $refundedQty);
        @endphp

        <div style="margin-top:8px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
          @if($existingReview)
            <div style="font-size:12px;color:#A16207;font-weight:700;display:inline-flex;align-items:center;gap:4px;">
              <span>Your rating:</span>
              <span style="display:inline-flex;align-items:center;gap:2px;">
                @for($star=1;$star<=5;$star++)
                  @if($star <= (int)$existingReview->rating)
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="#D97706" stroke="#D97706"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                  @else
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#D1D5DB" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                  @endif
                @endfor
              </span>
              @if($existingReview->comment)<span style="color:var(--text2);font-weight:400"> &bull; {{ $existingReview->comment }}</span>@endif
            </div>
            <button type="button" onclick="openRateModal({{ $it->product_id }}, '{{ addslashes($it->product_name) }}', {{ $existingReview->rating }}, '{{ addslashes($existingReview->comment ?? '') }}')" class="btn-p btn-line btn-sm" style="font-size:11px;padding:3px 8px;cursor:pointer;border-radius:6px;">
              Edit Rating
            </button>
          @else
            <button type="button" onclick="openRateModal({{ $it->product_id }}, '{{ addslashes($it->product_name) }}', 5, '')" class="btn-p btn-solid btn-sm" style="background:#16697A;color:#fff;border-radius:6px;font-size:11.5px;font-weight:700;padding:4px 12px;cursor:pointer;display:inline-flex;align-items:center;gap:4px;">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="#FBBF24" stroke="#FBBF24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
              Rate
            </button>
          @endif
        </div>

        @if($openReturn)
          <div style="margin-top:8px;padding:8px 12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;font-size:12px;color:#1e40af">
            <div style="font-weight:700;display:flex;align-items:center;gap:6px">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 5.5 5.5v0a5.5 5.5 0 0 1-5.5 5.5H11"/></svg>
              Return {{ ucfirst($openReturn->status) }} ({{ $openReturn->quantity }} item{{ $openReturn->quantity > 1 ? 's' : '' }})
            </div>
            <div style="font-size:11px;color:#3b82f6;margin-top:2px">
              Reason: {{ ucfirst(str_replace('_', ' ', $openReturn->reason)) }}
              @if($openReturn->reason_details) — {{ $openReturn->reason_details }}@endif
            </div>
            @if($openReturn->evidence_path)
              <div style="margin-top:6px;font-size:11px;color:#1e40af;display:flex;align-items:center;gap:6px">
                <b>Evidence:</b>
                <a data-image-viewer="document" href="{{ asset('storage/' . $openReturn->evidence_path) }}"   style="color:#2563eb;text-decoration:underline;font-weight:600;display:inline-flex;align-items:center;gap:3px">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                  View {{ $openReturn->evidence_type === 'video' ? 'Video' : 'Photo' }}
                </a>
              </div>
            @endif
            @if($openReturn->status === 'approved')
              <div style="margin-top:4px;font-size:11px;font-weight:600;color:#1e3a8a">Your return is approved. Logistics will arrange collection; follow its handoffs in the return center.</div>
            @elseif($openReturn->status === 'item_received')
              <div style="margin-top:4px;font-size:11px;font-weight:600;color:#1e3a8a">The seller received the item. Admin will record the refund transfer; confirm it once received.</div>
            @endif
            @if($openReturn->decision_note)
              <div style="margin-top:4px;font-size:11px;color:#1e3a8a"><b>Store note:</b> {{ $openReturn->decision_note }}</div>
            @endif
          </div>
        @elseif($refundedReturn)
          <div style="margin-top:8px;padding:8px 12px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:8px;font-size:12px;color:#065f46">
            <div style="font-weight:700;display:flex;align-items:center;gap:6px">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
              Refunded (₱{{ number_format($refundedReturn->refund_amount, 2) }})
            </div>
            <div style="font-size:11px;margin-top:2px">{{ $refundedReturn->quantity }} item(s) refunded.</div>
          </div>
        @endif

        @if(!$openReturn && $remainingQty > 0)
          <div style="margin-top:8px">
            <button type="button" onclick="openReturnModal({{ $it->id }})" style="display:inline-flex;align-items:center;gap:5px;padding:6px 11px;border:1px solid var(--border);border-radius:7px;background:#fff;color:var(--text);font-size:11px;font-weight:700;cursor:pointer">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 5.5 5.5v0a5.5 5.5 0 0 1-5.5 5.5H11"/></svg>
              Request Return / Refund
            </button>
          </div>
        @endif
      @endif
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
  @forelse(array_reverse($timeline) as $event)
    <div style="display:flex;gap:10px;margin-bottom:12px">
      <div style="display:flex;flex-direction:column;align-items:center">
        <span style="width:10px;height:10px;border-radius:50%;background:var(--primary,#16697A);flex-shrink:0;margin-top:4px"></span>
        @if(!$loop->last)<span style="width:2px;flex:1;background:var(--border-light);min-height:14px"></span>@endif
      </div>
      <div>
        <div style="font-weight:800;font-size:13px;text-transform:uppercase">{{ $event['title'] }}</div>
        @if($event['note'])<div style="font-size:12px;color:var(--text2)">{{ $event['note'] }}</div>@endif
        <div style="font-size:11px;color:var(--text3)">{{ $event['at']?->format('Y-m-d H:i') }}</div>
      </div>
    </div>
  @empty
    <div style="font-size:12px;color:var(--text3)">No status updates yet.</div>
  @endforelse
</div>

@if($o->isCancellableByBuyer())
  <form method="POST" action="{{ url('/orders/'.$o->id.'/cancel') }}" style="margin-top:12px;padding:14px;background:#fff;border:1px solid var(--border);border-radius:12px">
    @csrf
    <fieldset style="border:0;padding:0;margin:0">
      <legend style="font-size:13px;font-weight:800;color:var(--text)">Why are you cancelling?</legend>
      <div style="display:grid;gap:7px;margin-top:9px">
        @foreach(['changed_mind'=>'Changed my mind','ordered_by_mistake'=>'I ordered by mistake','found_better_price'=>'I found a better price','delivery_too_long'=>'Delivery is taking too long','wrong_item'=>'I ordered the wrong item','other'=>'Other reason'] as $reasonValue => $reasonLabel)
          <label style="display:flex;align-items:center;gap:9px;padding:8px 10px;border:1px solid var(--border);border-radius:8px;font-size:12px;cursor:pointer">
            <input type="radio" name="reason" value="{{ $reasonValue }}" required @checked(old('reason') === $reasonValue) style="accent-color:var(--danger)">{{ $reasonLabel }}
          </label>
        @endforeach
      </div>
    </fieldset>
    @error('reason')<div style="margin-top:6px;color:#b91c1c;font-size:11px">{{ $message }}</div>@enderror
    <div id="cancel-other-wrap" style="display:{{ old('reason') === 'other' ? 'block' : 'none' }};margin-top:9px">
      <label for="cancel-reason-details" style="display:block;font-size:11px;font-weight:700;margin-bottom:4px">Tell us a little more</label>
      <textarea id="cancel-reason-details" name="reason_details" maxlength="400" rows="2" style="width:100%;padding:9px;border:1px solid var(--border);border-radius:8px;font:inherit;font-size:12px">{{ old('reason_details') }}</textarea>
      @error('reason_details')<div style="margin-top:5px;color:#b91c1c;font-size:11px">{{ $message }}</div>@enderror
    </div>
    <button type="submit" style="width:100%;margin-top:11px;padding:11px;border-radius:999px;background:#fff;color:var(--danger);border:1.5px solid var(--danger);font-weight:700;cursor:pointer;font-size:13px;font-family:inherit">Continue to Cancel Order</button>
  </form>
  <script src="{{ asset('assets/buyer/js/order.js') }}"></script>
@elseif(! in_array($o->status, ['cancelled','delivered']))
  <div style="margin-top:12px;padding:11px;border-radius:10px;background:#f3f4f6;color:var(--text2);font-size:12px;text-align:center">This order is {{ str_replace('_',' ',$o->status) }} and can no longer be cancelled. Contact the seller if you need help.</div>
@endif

@if($o->status === 'delivered')
  @foreach($o->items as $it)
    @php
      $itemReturns = ($returns ?? collect())->get($it->id, collect());
      $openReturn = $itemReturns->first(fn($r) => in_array($r->status, ['requested', 'approved', 'item_received']));
      $refundedQty = (int) $itemReturns->where('status', 'refunded')->sum('quantity');
      $remainingQty = max(0, (int)$it->quantity - $refundedQty);
    @endphp
    @if(!$openReturn && $remainingQty > 0)
      <div id="return-modal-{{ $it->id }}" class="return-modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:16px;">
        <div style="background:#fff;border-radius:14px;max-width:480px;width:100%;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);padding:20px;max-height:90vh;overflow-y:auto">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;border-bottom:1px solid var(--border-light);padding-bottom:10px">
            <h3 style="margin:0;font-size:16px;font-weight:800;color:var(--text)">Request Return / Refund</h3>
            <button type="button" onclick="closeReturnModal({{ $it->id }})" style="background:none;border:none;cursor:pointer;color:var(--text2);font-size:22px;line-height:1">&times;</button>
          </div>

          <div style="font-size:13px;margin-bottom:14px;padding:10px;background:#f9fafb;border-radius:8px">
            <div style="font-weight:700;color:var(--text)">{{ $it->product_name }}</div>
            @if($it->variant_label)<div style="font-size:11px;color:var(--text2);margin-top:2px">{{ $it->variant_label }}</div>@endif
            <div style="font-size:11px;color:var(--text3);margin-top:2px">Returnable quantity: up to {{ $remainingQty }}</div>
          </div>

          <form method="POST" action="{{ url('/orders/'.$o->id.'/returns') }}" enctype="multipart/form-data" onsubmit="return validateReturnForm({{ $it->id }})">
            @csrf
            <input type="hidden" name="order_item_id" value="{{ $it->id }}">

            <div style="margin-bottom:12px">
              <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px">Reason for return</label>
              <select name="reason" id="return-reason-{{ $it->id }}" onchange="handleReturnReasonChange({{ $it->id }})" required style="width:100%;padding:9px 10px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:#fff">
                <option value="damaged">Damaged item</option>
                <option value="wrong_item">Wrong item</option>
                <option value="ordered_wrong_size">I ordered the wrong size</option>
                <option value="ordered_wrong_color">I ordered the wrong color</option>
                <option value="received_wrong_size">Seller sent a different size</option>
                <option value="received_wrong_color">Seller sent a different color</option>
                <option value="not_as_described">Not as described</option>
                <option value="changed_mind">Changed my mind</option>
                <option value="other">Other</option>
              </select>
            </div>

            <div style="margin-bottom:12px">
              <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px">Quantity to return (up to {{ $remainingQty }})</label>
              <input type="number" name="quantity" min="1" max="{{ $remainingQty }}" value="1" required style="width:100%;padding:9px 10px;border:1px solid var(--border);border-radius:8px;font-size:13px">
            </div>

            <div id="return-details-wrap-{{ $it->id }}" style="margin-bottom:14px">
              <label id="return-details-label-{{ $it->id }}" style="display:block;font-size:12px;font-weight:700;margin-bottom:4px">Details (optional)</label>
              <textarea name="reason_details" id="return-details-{{ $it->id }}" rows="3" maxlength="300" placeholder="Please provide additional details about the issue…" style="width:100%;padding:9px 10px;border:1px solid var(--border);border-radius:8px;font-size:13px;resize:vertical"></textarea>
              <div id="return-details-help-{{ $it->id }}" style="font-size:11px;color:var(--text3);margin-top:3px">Max 300 characters.</div>
            </div>

            <div style="margin-bottom:14px">
              <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px">
                Photo or Video Evidence <span style="color:#b91c1c">*</span>
              </label>
              <input type="file" name="evidence" id="return-evidence-{{ $it->id }}" accept="image/*,video/*" required style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:8px;font-size:12px;background:#fff">
              <div style="font-size:11px;color:var(--text3);margin-top:4px">Upload a clear photo or short video showing the issue (JPG, PNG, WEBP, MP4, MOV, up to 50MB).</div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:8px;border-top:1px solid var(--border-light);padding-top:12px">
              <button type="button" onclick="closeReturnModal({{ $it->id }})" style="padding:8px 14px;border:1px solid var(--border);border-radius:8px;background:#fff;font-size:12px;font-weight:600;cursor:pointer">Keep Item</button>
              <button type="submit" style="padding:8px 16px;border:none;border-radius:8px;background:var(--primary,#16697A);color:#fff;font-size:12px;font-weight:700;cursor:pointer">Send Request</button>
            </div>
          </form>
        </div>
      </div>
    @endif
  @endforeach

  <script>
    function openReturnModal(itemId) {
      var modal = document.getElementById('return-modal-' + itemId);
      if (modal) modal.style.display = 'flex';
    }
    function closeReturnModal(itemId) {
      var modal = document.getElementById('return-modal-' + itemId);
      if (modal) modal.style.display = 'none';
    }
    function handleReturnReasonChange(itemId) {
      var select = document.getElementById('return-reason-' + itemId);
      var label = document.getElementById('return-details-label-' + itemId);
      var textarea = document.getElementById('return-details-' + itemId);
      if (select && label && textarea) {
        if (select.value === 'other') {
          label.innerHTML = 'Details <span style="color:#b91c1c">(required, at least 10 characters)</span>';
          textarea.required = true;
          textarea.minLength = 10;
        } else {
          label.textContent = 'Details (optional)';
          textarea.required = false;
          textarea.removeAttribute('minLength');
        }
      }
    }
    function validateReturnForm(itemId) {
      var select = document.getElementById('return-reason-' + itemId);
      var textarea = document.getElementById('return-details-' + itemId);
      var fileInput = document.getElementById('return-evidence-' + itemId);
      if (select && select.value === 'other') {
        if (!textarea || textarea.value.trim().length < 10) {
          alert('Please enter at least 10 characters explaining your return reason.');
          if (textarea) textarea.focus();
          return false;
        }
      }
      if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        alert('Please attach a photo or video evidence for your return request.');
        if (fileInput) fileInput.focus();
        return false;
      }
      return true;
    }
  </script>
@endif

@if($isDelivered)
  <!-- Rate Product Modal (matching mobile application) -->
  <div id="rateModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:9999;align-items:center;justify-content:center;padding:16px;">
    <div style="background:#fff;border-radius:14px;max-width:440px;width:100%;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1),0 10px 10px -5px rgba(0,0,0,0.04);overflow:hidden;">
      <div style="padding:16px 20px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between;">
        <h3 style="margin:0;font-size:16px;font-weight:800;color:#111827;">Rate this item</h3>
        <button type="button" onclick="closeRateModal()" style="border:none;background:transparent;cursor:pointer;color:#9CA3AF;font-size:22px;line-height:1;padding:0;">&times;</button>
      </div>
      <form id="rateModalForm" method="POST" action="" style="padding:20px;">
        @csrf
        <div id="rateModalProductName" style="font-size:14.5px;font-weight:700;color:#1F2937;margin-bottom:14px;text-align:center;"></div>
        
        <div style="display:flex;justify-content:center;align-items:center;gap:6px;margin-bottom:16px;">
          @for($s=1;$s<=5;$s++)
            <button type="button" class="rate-star-btn" data-star="{{ $s }}" style="border:none;background:transparent;cursor:pointer;padding:4px;outline:none;" title="{{ $s }} star{{ $s > 1 ? 's' : '' }}">
              <svg class="rate-star-icon" width="34" height="34" viewBox="0 0 24 24" fill="#FBBF24" stroke="#FBBF24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </button>
          @endfor
        </div>
        <input type="hidden" id="rateModalRating" name="rating" value="5">

        <div style="margin-bottom:18px;">
          <label for="rateModalComment" style="display:block;font-size:12px;font-weight:700;color:#4B5563;margin-bottom:6px;">Feedback (optional)</label>
          <textarea id="rateModalComment" name="comment" rows="3" maxlength="500" placeholder="Tell us what you liked about this item or where it could improve..." style="width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #D1D5DB;border-radius:8px;font-family:inherit;font-size:13px;resize:vertical;"></textarea>
        </div>

        <div style="display:flex;gap:10px;justify-content:flex-end;">
          <button type="button" onclick="closeRateModal()" class="btn-p btn-line" style="border-radius:8px;font-size:13px;padding:8px 16px;cursor:pointer;">Cancel</button>
          <button type="submit" class="btn-p btn-solid" style="background:#16697A;border-radius:8px;font-size:13px;font-weight:700;padding:8px 20px;cursor:pointer;">Submit</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    var currentSelectedRating = 5;
    function setModalRating(val) {
      currentSelectedRating = val;
      var ratingInput = document.getElementById('rateModalRating');
      if (ratingInput) ratingInput.value = val;
      updateStarDisplay(val);
    }
    function updateStarDisplay(val) {
      var starBtns = document.querySelectorAll('.rate-star-btn');
      starBtns.forEach(function(btn) {
        var s = parseInt(btn.getAttribute('data-star') || '1', 10);
        var icon = btn.querySelector('.rate-star-icon');
        if (icon) {
          if (s <= val) {
            icon.setAttribute('fill', '#FBBF24');
            icon.setAttribute('stroke', '#FBBF24');
          } else {
            icon.setAttribute('fill', 'none');
            icon.setAttribute('stroke', '#D1D5DB');
          }
        }
      });
    }
    function openRateModal(productId, productName, currentRating, currentComment) {
      var modal = document.getElementById('rateModal');
      var form = document.getElementById('rateModalForm');
      var nameEl = document.getElementById('rateModalProductName');
      var commentEl = document.getElementById('rateModalComment');

      if (form) form.action = '{{ url("/orders/".$o->id."/rate") }}/' + productId;
      if (nameEl) nameEl.textContent = productName;
      if (commentEl) commentEl.value = currentComment || '';
      setModalRating(currentRating || 5);

      if (modal) modal.style.display = 'flex';
    }
    function closeRateModal() {
      var modal = document.getElementById('rateModal');
      if (modal) modal.style.display = 'none';
    }

    document.addEventListener('DOMContentLoaded', function() {
      var starBtns = document.querySelectorAll('.rate-star-btn');
      starBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
          var starVal = parseInt(this.getAttribute('data-star') || '5', 10);
          setModalRating(starVal);
        });
        btn.addEventListener('mouseenter', function() {
          var starVal = parseInt(this.getAttribute('data-star') || '5', 10);
          updateStarDisplay(starVal);
        });
        btn.addEventListener('mouseleave', function() {
          updateStarDisplay(currentSelectedRating);
        });
      });

      if (window.location.hash === '#rate') {
        var rateSec = document.getElementById('rate');
        if (rateSec) {
          rateSec.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      }
    });
  </script>
@endif
@endsection

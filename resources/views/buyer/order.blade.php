@extends('buyer.layouts.website')
@section('content')
@php
  $o = $order;
  $pay = $o->payment;
  $del = $o->deliveries->first();
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

@if($o->deliveries->isNotEmpty())
  <div style="background:#fff;border:1px solid var(--border);border-radius:10px;padding:14px;margin-top:12px">
    <div style="font-weight:800;font-size:14px">Shipment Tracking</div>
    <div style="font-size:11px;color:var(--text2);margin-top:4px">Use the tracking number when contacting the courier or logistics center.</div>
    @foreach($o->deliveries as $shipment)
      <div style="border-top:1px solid var(--border-light);margin-top:10px;padding-top:10px;font-size:12px">
        @if($o->deliveries->count() > 1)
          <div style="font-weight:700;margin-bottom:4px">Package {{ $loop->iteration }} of {{ $o->deliveries->count() }}</div>
        @endif
        <div>Status: <b style="text-transform:uppercase">{{ str_replace('_',' ',$shipment->status) }}</b></div>
        @if($shipment->tracking_number)
          <div style="margin-top:3px">Tracking number: <b style="font-family:monospace;font-size:13px;letter-spacing:.03em">{{ $shipment->tracking_number }}</b></div>
        @else
          <div style="margin-top:3px;color:var(--text2)">Tracking number will appear once the parcel is booked.</div>
        @endif
      </div>
    @endforeach
  </div>
@endif

@if($del && $del->parcel_status)
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
@endif

@if($o->status === 'cancelled')
  <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px;margin-top:12px;color:#991b1b;font-weight:700;font-size:13px">This order was cancelled.@if($o->cancellation_reason)<div style="margin-top:5px;font-weight:500">Reason: {{ $o->cancellation_reason }}</div>@endif</div>
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
      @if($o->status === 'delivered')
        @php
          $existingReview = ($reviews ?? collect())->get($it->product_id);
          $itemReturns = ($returns ?? collect())->get($it->id, collect());
          $openReturn = $itemReturns->first(fn($r) => in_array($r->status, ['requested', 'approved', 'item_received']));
          $refundedReturn = $itemReturns->first(fn($r) => $r->status === 'refunded');
          $refundedQty = (int) $itemReturns->where('status', 'refunded')->sum('quantity');
          $remainingQty = max(0, (int)$it->quantity - $refundedQty);
        @endphp

        @if($existingReview)
          <div style="margin-top:8px;font-size:12px;color:#A16207;font-weight:700;display:flex;align-items:center;gap:4px;">
            <span>Your rating:</span>
            <span style="display:inline-flex;align-items:center;gap:2px;">
              @for($star=1;$star<=5;$star++)
                @if($star <= (int)$existingReview->rating)
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="#A16207" stroke="#A16207"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                @else
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#D8D5CC" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                @endif
              @endfor
            </span>
            @if($existingReview->comment)<span style="color:var(--text2);font-weight:400"> · {{ $existingReview->comment }}</span>@endif
          </div>
        @else
          <form method="POST" action="{{ url('/orders/'.$o->id.'/rate/'.$it->product_id) }}" style="margin-top:8px;display:flex;align-items:center;gap:7px;flex-wrap:wrap">
            @csrf
            <label for="rating-{{ $it->product_id }}" style="font-size:11px;color:var(--text2);font-weight:700">Rate this product</label>
            <select id="rating-{{ $it->product_id }}" name="rating" required style="padding:6px 8px;border:1px solid var(--border);border-radius:7px;background:#fff;font-size:11px">
              <option value="">Choose stars</option>
              @for($star=5;$star>=1;$star--)<option value="{{ $star }}">{{ $star }} {{ $star === 1 ? 'star' : 'stars' }}</option>@endfor
            </select>
            <input type="text" name="comment" maxlength="2000" placeholder="Write a review (optional)" style="flex:1;min-width:150px;padding:6px 8px;border:1px solid var(--border);border-radius:7px;font-size:11px">
            <button type="submit" style="padding:7px 11px;border:0;border-radius:7px;background:var(--green);color:#fff;font-size:11px;font-weight:700;cursor:pointer">Submit rating</button>
          </form>
        @endif

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
                <a href="{{ asset('storage/' . $openReturn->evidence_path) }}" target="_blank" rel="noopener" style="color:#2563eb;text-decoration:underline;font-weight:600;display:inline-flex;align-items:center;gap:3px">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                  View {{ $openReturn->evidence_type === 'video' ? 'Video' : 'Photo' }}
                </a>
              </div>
            @endif
            @if($openReturn->status === 'approved')
              <div style="margin-top:4px;font-size:11px;font-weight:600;color:#1e3a8a">The seller approved your return. Please contact the seller via chat to arrange return handover.</div>
            @elseif($openReturn->status === 'item_received')
              <div style="margin-top:4px;font-size:11px;font-weight:600;color:#1e3a8a">The seller received the item. Refund will be settled shortly.</div>
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
@endsection

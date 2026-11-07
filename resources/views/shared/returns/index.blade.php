@extends($layout)
@section('title', 'Return center')
@section('content')
<div class="return-center">
    <header class="review-head"><div><p class="review-eyebrow">Orders &amp; settlements</p>
    <h1>Return center</h1>
    <p>Track collection, hub handoffs, seller inspection and the actual refund payment. New return services use a separate agreed and funded budget; riders collect no cash.</p></div></header>
    @if(\Illuminate\Support\Facades\Schema::hasTable('return_settlements'))<nav class="return-nav"><a class="review-action" href="{{ route('returns.remittances') }}">Return payments &amp; earnings</a><a class="review-action" href="{{ route('returns.guide') }}">Button-by-button guide</a></nav>@endif
    @if(session('success'))<p role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
    @if($escrowIssues->isNotEmpty())
        <article class="review-warning"><h2>Existing GCash orders need funding review</h2><p>These older orders advanced without a confirmed escrow deposit. Further fulfillment and payouts remain blocked until the seller remits the full payment and admin verifies the funds.</p>
            @foreach($escrowIssues as $issue)<p>Order #{{ $issue->id }} · {{ $issue->status }}</p>@endforeach
            <a class="review-action" href="{{ route('admin.gcash.index') }}">Review GCash funding</a>
        </article>
    @endif
    @forelse($returns as $case)
        @php
            $flow = $case->workflow;
            $stage = $flow?->stage;
            $staff = $flow && \App\Support\LogisticsAccess::isCenterStaff($user, (int)$flow->center_id);
            $actions = [];
            $agreement = \Illuminate\Support\Facades\Schema::hasTable('return_settlements') ? $case->settlement : null;
            $managed = \Illuminate\Support\Facades\Schema::hasTable('return_settlements') && $case->quantity > 0
                && ($agreement || ($case->isOpen() && (!$flow || $stage === 'awaiting_collection')));
            $ready = !$managed || ($agreement?->funded_at && $agreement->agreed());
            if ($case->status === 'approved' && $flow && !$flow->center_id && $user->isLogisticsAdmin()) $actions['route_hub'] = 'Route to collection hub';
            if ($case->status === 'approved' && $staff && $ready) {
                if (in_array($stage, ['awaiting_collection', 'pickup_assigned'])) $actions['assign'] = 'Assign collection rider';
                if ($stage === 'collected') $actions['intake'] = 'Confirm hub receipt';
                if ($stage === 'at_hub') $actions += ['transfer' => 'Dispatch to another hub', 'dispatch_seller' => 'Dispatch back to seller'];
            }
            if ($flow && $stage === 'in_transfer' && \App\Support\LogisticsAccess::isCenterStaff($user, (int)$flow->destination_center_id)) $actions['receive_transfer'] = 'Confirm arrival at this hub';
            if ($flow && $stage === 'pickup_assigned' && $user->role === 'rider' && (int)$flow->rider_id === (int)$user->rider?->id) $actions['pickup'] = 'Confirm collected from buyer';
            if (in_array($stage, ['sent_to_seller', 'seller_arrival']) && (int)$case->seller_id === (int)$user->id && $ready) $actions['receive'] = 'Confirm physical receipt';
            if ($stage === 'sent_to_seller' && $user->role === 'rider' && (int)$flow->rider_id === (int)$user->rider?->id) $actions['deliver_seller'] = 'Record delivery to seller';
            if (($case->status === 'item_received' || ($managed && $agreement && !$agreement->physicalReturn() && $stage === 'refund_pending')) && !$flow?->refund_sent_at && $user->isAdmin() && $ready && $agreement?->resolution !== 'exchange' && (!$managed || $case->refund_amount > 0)) $actions['refund'] = 'Record actual refund transfer';
            if ($flow?->refund_sent_at && !$flow->buyer_confirmed_at && (int)$case->buyer_id === (int)$user->id) $actions['confirm_refund'] = 'I received the refund';
        @endphp
        <article>
            <div class="return-heading"><h2>Return #{{ $case->id }} <small>Order #{{ $case->order_id }}</small></h2><strong>{{ peso($case->refund_amount) }}</strong></div>
            <p>{{ $case->item?->product_name ?? 'Purchased item' }} · {{ $case->quantity > 0 ? $case->quantity.' item(s)' : 'Payment adjustment; no return shipment' }}</p>
            @if($managed)<a class="review-action" href="{{ route('returns.agreement', $case) }}">View agreement, fees &amp; funding</a>@endif
            @if($managed && !$ready && !$agreement?->closed_at)<p class="review-warning">Agree on the resolution and verify funding before scheduling or continuing return transport.</p>@endif
            <details><summary>Parcel, collection address and return destination</summary>
                @php($original = \App\Support\EscrowEarnings::deliveryForSeller($case->order, (int)$case->seller_id))
                <p>Reason: {{ ucwords(str_replace('_', ' ', $case->reason)) }}. {{ $case->reason_details }}</p>
                <p>Collect from: {{ $original?->recipient_name ?? $case->buyer?->name }} · {{ $original?->recipient_phone }}<br>{{ $original?->recipient_address ?? $case->order->address?->address_line }}</p>
                <p>Return to: {{ $original?->sender_name }} · {{ $original?->sender_phone }}<br>{{ $original?->sender_address }}</p>
                @if($case->evidence_url)<a data-image-viewer="document" class="review-action" href="{{ $case->evidence_url }}"  >View buyer evidence</a>@endif
                @if($case->decision_note)<p>Decision note: {{ $case->decision_note }}</p>@endif
            </details>
            <p class="review-status"><b>{{ ucwords(str_replace('_', ' ', $case->status)) }}</b> · {{ ucwords(str_replace('_', ' ', $stage ?? ($case->status === 'refunded' ? 'Legacy refund record; payment proof unverified' : 'Awaiting seller review'))) }}</p>
            @if($flow?->center_id)<p>Handling hub #{{ $flow->center_id }} @if($flow->destination_center_id) → Hub #{{ $flow->destination_center_id }} @endif @if($flow->rider_id) · Rider #{{ $flow->rider_id }} @endif</p>@endif
            @if($flow?->transport_reference)<p>Transport reference: {{ $flow->transport_reference }}</p>@endif
            @if($flow?->refund_reference)
                <p>Refund transfer: {{ $flow->refund_reference }} · {{ $flow->refund_sent_at?->format('M j, Y g:i a') }}</p>
                @if($user->isAdmin() || (int)$case->buyer_id === (int)$user->id)<a data-image-viewer="document" class="review-action" href="{{ route('returns.proof', $case) }}"  >View refund payment proof</a>@endif
            @endif
            @if($stage === 'completed_no_refund')<p>This item had no refundable cash value after discounts. The return is closed; no payment transfer is due.</p>@endif
            @if($case->status === 'requested' && (int)$case->seller_id === (int)$user->id)<a class="review-action" href="{{ route('seller.returns.index') }}">Review this request</a>@endif
            @if($actions)
                <form method="post" action="{{ route('returns.action', $case) }}" enctype="multipart/form-data">
                    @csrf
                    <label>Next step<select name="action">@foreach($actions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                    @if(isset($actions['assign']))
                        <label>Collection rider<select name="rider_id"><option value="">Choose a rider</option>@foreach(\App\Models\Rider::where('center_id', $flow->center_id)->whereNotNull('approved_at')->where('is_verified', true)->where('status', '!=', 'inactive')->get() as $rider)<option value="{{ $rider->id }}">{{ $rider->name }} (#{{ $rider->id }})</option>@endforeach</select></label>
                    @endif
                    @if(isset($actions['route_hub']))
                        <label>Collection hub<select name="destination_center_id"><option value="">Choose a hub</option>@foreach(\App\Models\LogisticsCenter::orderBy('name')->get() as $hub)<option value="{{ $hub->id }}">{{ $hub->name }}</option>@endforeach</select></label>
                    @elseif(isset($actions['transfer']))
                        <label>Transfer destination<select name="destination_center_id"><option value="">Choose a hub for a transfer</option>@foreach(\App\Models\LogisticsCenter::where('logistics_company_id', \App\Models\LogisticsCenter::whereKey($flow->center_id)->value('logistics_company_id'))->where('id', '!=', $flow->center_id)->get() as $hub)<option value="{{ $hub->id }}">{{ $hub->name }} (#{{ $hub->id }})</option>@endforeach</select></label>
                    @endif
                    @if(isset($actions['transfer']) || isset($actions['dispatch_seller']) || isset($actions['refund']))<label>Transport / payment reference<input name="reference" maxlength="150"></label>@endif
                    @if($managed && isset($actions['transfer']))<label>Inter-hub return driver<select name="driver_id"><option value="">Choose the driver for the quoted leg</option>@foreach(\App\Models\HubTransportDriver::where('logistics_company_id', \App\Models\LogisticsCenter::whereKey($flow->center_id)->value('logistics_company_id'))->where('status','!=','inactive')->get() as $driver)<option value="{{ $driver->id }}">{{ $driver->name }}</option>@endforeach</select></label>@endif
                    @if($managed && isset($actions['dispatch_seller']))<label>Rider delivering back to seller<select name="rider_id"><option value="">Choose an approved rider</option>@foreach(\App\Models\Rider::where('center_id', $flow->center_id)->whereNotNull('approved_at')->get() as $rider)<option value="{{ $rider->id }}">{{ $rider->name }}</option>@endforeach</select></label>@endif
                    @if(isset($actions['deliver_seller']) || ($managed && isset($actions['pickup'])))<label>Parcel handover photo<input name="proof" type="file" accept="image/jpeg,image/png,image/webp" required></label>@endif
                    @if(isset($actions['receive']))<label class="return-check"><input type="checkbox" name="restock" value="1"> Inspected and resellable: restore this quantity to stock</label>@endif
                    @if(isset($actions['refund']))
                        @php($funding = \App\Services\ReturnWorkflowService::fundingSummary($case->order, (float)$case->refund_amount))
                        <p><b>Additional escrow funding required before refund: {{ peso($funding['shortfall']) }}</b><br>Original reconciled payment: {{ peso($funding['funded']) }}. Completed logistics still reserved: {{ peso($funding['reserved_logistics']) }}. Recheck before transferring money; balances are validated again when submitted.</p>
                        <p>For new agreements, use the private verified receiving account on the agreement page. Transfer the exact net refund and upload proof only after the payment succeeds. Completed logistics fees remain payable; any funding shortfall must first be recovered into escrow.</p>
                        <label>Buyer refund transfer proof<input type="file" name="proof" accept="image/jpeg,image/png,image/webp" required></label>
                        <details @if($funding['shortfall'] > 0) open @endif><summary>Record additional escrow funding / recovered money</summary>
                            <label>Amount actually recovered into escrow<input type="number" name="recovery_amount" min="0" step="0.01"></label>
                            <label>Incoming recovery transfer reference<input name="recovery_reference" maxlength="150"></label>
                            <label>Verified incoming recovery proof<input type="file" name="recovery_proof" accept="image/jpeg,image/png,image/webp"></label>
                        </details>
                        <label class="return-check"><input type="checkbox" name="confirm_transfer" value="1" required> I verified that the exact refund was sent to the buyer and that any recovery funds actually reached escrow.</label>
                    @endif
                    <button type="submit">Confirm step</button>
                </form>
            @endif
            @if($flow?->events)<details><summary>Handoff history</summary><ol>@foreach($flow->events as $event)<li>{{ ucwords(str_replace('_', ' ', $event['action'])) }} · {{ $event['at'] }} · Account #{{ $event['actor_id'] }} @if($event['reference'] ?? null) · {{ $event['reference'] }} @endif
                @if(($event['proof_path'] ?? null) && in_array($event['action'], ['pickup','deliver_seller']) && \Illuminate\Support\Facades\Schema::hasTable('return_settlements'))<a class="review-action" data-image-viewer="document" href="{{ route('returns.file', ['kind'=>'custody','id'=>$case->id,'event'=>$loop->index]) }}">View handover photo</a>@endif
            </li>@endforeach</ol></details>@endif
        </article>
    @empty<p>No return requests to display.</p>@endforelse
    {{ $returns->links() }}
</div>
<style>
.return-center{max-width:1000px;margin:0 auto;padding:20px;color:#19343b;overflow-wrap:anywhere}.return-center h1{font-size:26px;font-weight:800}.return-center>p{font-size:14px;color:#617278;margin:10px 0 22px}.return-center article{padding:20px;margin:16px 0;border:1px solid #dce3e2;border-radius:14px;background:white}.return-heading{display:flex;justify-content:space-between;gap:12px;align-items:start}.return-heading h2{font-size:17px;font-weight:700}.return-heading small{display:block;font-size:12px;color:#687579;font-weight:400}.return-center article p{font-size:13px;margin:8px 0}.return-center a{color:#176879;text-decoration:underline}.return-center form{display:grid;gap:12px;margin-top:18px;padding-top:16px;border-top:1px solid #e7ebea}.return-center label{display:grid;gap:5px;font-size:13px}.return-center input,.return-center select{min-width:0;width:100%;max-width:100%;padding:9px;border:1px solid #cad5d4;border-radius:7px;background:white;color:#19343b}.return-center .return-check{display:flex;align-items:center;gap:8px}.return-center [type=checkbox]{width:16px;height:16px;min-width:16px;padding:0;border-radius:3px;appearance:auto;flex-shrink:0}.return-center button{justify-self:start;border:1px solid #9cb4b7;border-radius:8px;padding:9px 16px;background:#f3f7f6;font-weight:700;color:#17515c}.return-center details{font-size:13px;margin-top:12px}.return-center summary{cursor:pointer;font-weight:600}.return-center ol{padding-left:20px}.return-center details label{margin-top:12px}@media(max-width:600px){.return-center{padding:10px}.return-center article{padding:14px}.return-heading{flex-wrap:wrap}}
</style>
<link rel="stylesheet" href="{{ asset('assets/shared/css/workflow-review.css') }}?v={{ filemtime(public_path('assets/shared/css/workflow-review.css')) }}">
@endsection

@extends($layout)
@section('title', 'Return proposal & payment')
@section('content')
@php($editable = !$agreement->funded_at && !$agreement->closed_at && !$agreement->payments->whereIn('status',['submitted','verified'])->count())
<div class="return-center return-agreement return-flow-ui">
    <header class="review-head"><div><p class="review-eyebrow">Return #{{ $returnRequest->id }} · Order #{{ $returnRequest->order_id }}</p>
        <h1>Return proposal &amp; payment</h1></div></header>
<details class="return-disclosure return-introduction"><summary>How this works</summary><div class="return-disclosure-body"><p>@if((int)$agreement->policy_version>=2)Agree on responsibility and the remedy. Buyer-fault returns deduct original seller-funded costs and new delivery; the seller sends the net refund directly. Seller-fault remedies keep their existing funding process.@else Agree on the resolution. This earlier agreement keeps its recorded separate-payment terms; it is not converted into new deductions.@endif</p></div></details>
    <nav class="return-nav"><a class="review-action" href="{{ route('returns.center') }}">Return center</a><a class="review-action" href="{{ route('returns.remittances') }}">Remittances</a><a class="review-action" href="{{ route('returns.guide') }}">How this works</a></nav>
    @if(session('success'))<p role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<p class="review-warning" role="alert">{{ $errors->first() }}</p>@endif
    <div class="return-case-context"><div class="return-heading"><h2>{{ $returnRequest->item?->product_name ?? 'Purchased item' }}<small>Quantity: {{ $returnRequest->quantity }} · Proposal version {{ $agreement->version }}</small></h2><b>{{ ucwords(str_replace('_',' ',$agreement->status)) }}</b></div></div>
@if($agreement->dispute_note)<p class="review-warning"><b>Payment/custody issue:</b> {{ $agreement->dispute_note }}. Further steps are paused until reviewed.</p>@endif
<details class="return-disclosure return-case-record"><summary>Agreement details &amp; amounts</summary><div class="return-disclosure-body">

        <p><b>Buyer’s reason:</b> {{ ucwords(str_replace('_',' ',$returnRequest->reason)) }}. {{ $returnRequest->reason_details }}</p>
        @if($returnRequest->item?->variant_label)<p><b>Purchased variation:</b> {{ $returnRequest->item->variant_label }}</p>@endif
        @if($agreement->status==='draft')<p>Waiting for the seller’s proposal. No fee payment or collection is required yet.</p>
        @else
            <p><b>Resolution:</b> {{ ucwords(str_replace('_',' ',$agreement->resolution)) }} · <b>Responsibility:</b> {{ ucfirst($agreement->fault) }} · <b>Return-fee payer:</b> {{ ucfirst($agreement->payer) }}</p>
            <p>Buyer: {{ $agreement->buyer_accepted_at ? 'Agreed' : 'Awaiting agreement' }} · Seller: {{ $agreement->seller_accepted_at ? 'Agreed' : 'Awaiting agreement' }}. No admin approval of responsibility or fees is required.</p>
            @if($agreement->reason_note)<p><b>Seller’s proposal:</b> {{ $agreement->reason_note }}</p>@endif
            @if($agreement->replacement_description)<p><b>Replacement:</b> {{ $agreement->replacement_description }}</p>@endif
            @if(!$agreement->physicalReturn())<p>The buyer keeps the item. No return transport fee, collection or stock restoration applies.</p>@endif
        @endif
        <dl class="return-money">
            <div><dt>Item refund</dt><dd>{{ peso($agreement->merchandise_cents/100) }}</dd></div>
            <div><dt>Original shipping reimbursement</dt><dd>{{ peso($agreement->shipping_cents/100) }}</dd></div>
            <div><dt>Buyer receives</dt><dd>{{ peso($agreement->net_refund_cents/100) }}</dd></div>
        </dl>
        <p>Return fees are paid separately. They are not deducted from the refund or the seller’s held earnings.</p>

    </div></details>

    <details class="return-disclosure"><summary><h2>{{ $agreement->quoted_at ? 'Automatic return fees' : 'Estimated return fees' }}</h2></summary><div class="return-disclosure-body">
        <p>Rates come from the Commission settings. Pickup is charged for the returned quantity; hub transfer and receiving-hub fees apply only to a route between different hubs. These amounts are fixed when the seller sends the proposal.</p>
        @if($feeError)<p class="review-warning">{{ $feeError }}</p>@endif
        @if($feePreview)
            @if($agreement->physicalReturn())<p><b>Route:</b> {{ $feePreview['collection_hub_name']??'Buyer-side hub' }} → {{ $feePreview['seller_hub_name']??'Seller-side hub' }}</p>@endif
            <table class="return-fees"><thead><tr><th scope="col">Service</th><th scope="col">Amount</th></tr></thead><tbody>
            @forelse($feePreview['lines'] as $line)<tr><td>{{ ucwords(str_replace('_',' ',$line['role'])) }}</td><td>{{ peso($line['amount_cents']/100) }}</td></tr>
            @empty<tr><td>No paid transport required</td><td>{{ peso(0) }}</td></tr>@endforelse
            </tbody><tfoot><tr><th scope="row">Total return fees</th><td>{{ peso($feePreview['total_cents']/100) }}</td></tr></tfoot></table>
        @endif
    </div></details>

    @include('shared.returns.partials.propose')

    @if($buyer && $editable && $agreement->status==='proposed' && !$agreement->buyer_accepted_at && !$agreement->dispute_note)
    <details class="return-task" name="return-current-task" open><summary><h2>Review the seller’s proposal</h2></summary><div class="return-disclosure-body"><p>Check responsibility, refund and the full service breakdown. @if($agreement->payer==='buyer')If you agree, you will pay <strong class="return-action-amount">{{ peso($agreement->budgetCents()/100) }}</strong> separately for the return.@else The seller pays the return fees.@endif</p>
        <form method="post" action="{{ route('returns.agreement.update',$returnRequest) }}">@include('shared.returns.partials.form-identity',['action'=>'accept'])
            @include('shared.returns.partials.confirm',['confirmation'=>'I agree to this proposal, who pays the fees, and the complete fee breakdown.'])<button type="submit">Accept agreement</button>
        </form>
    </div></details>@endif

    @if(($buyer || $seller) && $editable && !$agreement->closed_at)
    <details><summary>Suggest changes or decline</summary><p>Your response is sent to the other party. The seller can edit and resend; neither party is charged until they agree.</p>
        <form method="post" action="{{ route('returns.agreement.update',$returnRequest) }}">@csrf<input type="hidden" name="version" value="{{ $agreement->version }}">
            <label>Response<select name="action"><option value="comment">Suggest a change</option><option value="decline">Decline {{ $seller && $agreement->status==='draft' ? 'this return request' : 'this proposal' }}</option></select></label>
            <label>Comment or suggestion<textarea name="note" minlength="10" maxlength="1500" required></textarea></label>
            @include('shared.returns.partials.confirm')<button type="submit">Send response</button>
        </form>
    </details>@endif

    @if($agreement->agreed() && !$agreement->funded_at && !$agreement->closed_at)
    <details class="return-task" name="return-current-task" {{ (($buyer && $agreement->payer==='buyer') || ($seller && $agreement->payer==='seller')) && $budgetOutstanding>0 && !$agreement->payments->contains(fn($p)=>$p->purpose==='return_budget' && $p->status==='submitted') ? 'open' : '' }}><summary><h2>Return transport fee — paid by {{ $agreement->payer }}</h2></summary><div class="return-disclosure-body"><p><b>Total return transport:</b> {{ peso($agreement->budgetCents()/100) }}, covering the listed riders and logistics services. Payment status is shown below.</p>
        @php($payer = ($buyer && $agreement->payer==='buyer') || ($seller && $agreement->payer==='seller'))
        @php($pendingFee = $agreement->payments->first(fn($p)=>$p->purpose==='return_budget' && $p->status==='submitted'))
        @if($pendingFee)<p class="review-status">Return-fee receipt submitted — awaiting admin verification. Do not transfer or upload the same payment again.</p>
        @elseif($payer && $budgetOutstanding>0)
            @include('shared.returns.partials.payment-upload',['paymentPurpose'=>'return_budget','paymentCents'=>$budgetOutstanding,'receiptLabel'=>'Return-fee receipt screenshot','uploadButton'=>'Submit return-fee receipt'])
        @elseif($budgetOutstanding===0)<p>Return fees are secured. Waiting for the refund account or original refund funds if still required.</p>
        @else<p>Waiting for the {{ $agreement->payer }} to transfer the fee and upload their receipt from their account.</p>@endif
        <p>Admin reviews the uploaded receipt and confirms the actual transfer. Collection unlocks once the fees, buyer refund and refund account are secured.</p>
    </div></details>
    @if(($seller || $admin) && $funding['shortfall']>0)
    <details class="return-task" name="return-current-task" {{ $seller && !$agreement->payments->contains(fn($p)=>$p->purpose==='refund_recovery' && $p->status==='submitted') ? 'open' : '' }}><summary><h2>Seller funds for the buyer’s item refund</h2></summary><div class="return-disclosure-body">
        <p><b>Seller needs to restore {{ peso($funding['shortfall']) }}.</b> The original order proceeds have already been released or are unavailable. This payment puts the money needed for the buyer’s refund back into the platform account.</p>
        <p>This is separate from the {{ peso($agreement->budgetCents()/100) }} return transport fee. The buyer does not pay this item-refund amount again.</p>
        @php($pendingRecovery = $agreement->payments->first(fn($p)=>$p->purpose==='refund_recovery' && $p->status==='submitted'))
        @if($pendingRecovery)<p class="review-status">Seller refund-funding receipt submitted — awaiting admin verification.</p>
        @elseif($seller)
            @include('shared.returns.partials.payment-upload',['paymentPurpose'=>'refund_recovery','paymentCents'=>\App\Services\ReturnSettlementService::cents($funding['shortfall']),'receiptLabel'=>'Seller refund-funding receipt screenshot','uploadButton'=>'Submit refund-funding receipt'])
        @else<p>Waiting for the seller to upload their refund-funding receipt. Review it below once submitted.</p>@endif
    </div></details>
    @endif
    @endif

    @if($buyer && !$agreement->closed_at && $agreement->resolution!=='exchange' && (!$agreement->funded_at || !$agreement->destination_verified_at))
    <details class="return-task" name="return-current-task" open><summary><h2>Where to send your refund</h2></summary><div class="return-disclosure-body"><p>Only you and payment staff can see the complete account. Never enter a PIN, password or OTP.</p>
        <form method="post" action="{{ route('returns.agreement.update',$returnRequest) }}">@include('shared.returns.partials.form-identity',['action'=>'destination'])
            @foreach(['provider'=>'Bank / e-wallet','holder'=>'Account holder','account'=>'Account / e-wallet number'] as $key=>$label)<label>{{ $label }}<input name="{{ $key }}" value="{{ $destination[$key]??'' }}" maxlength="{{ $key==='holder'?150:100 }}" required autocomplete="off"></label>@endforeach
            @include('shared.returns.partials.confirm')<button type="submit">Verify refund account</button>
        </form>
    </div></details>@endif
    @if($admin && $destination)<details class="return-disclosure"><summary><h2>Verified buyer receiving details</h2></summary><div class="return-disclosure-body"><p>{{ $destination['provider'] }} · {{ $destination['holder'] }}<br>{{ $destination['account'] }}</p><p>Record the actual refund transfer in the Return center after physical inspection or an agreed keep-item resolution.</p></div></details>@endif
    @if($buyer && $agreement->funded_at && $destination)<details class="return-disclosure"><summary><h2>Your refund account</h2></summary><div class="return-disclosure-body"><p>{{ $destination['provider'] }} · {{ $destination['holder'] }}<br>{{ $destination['account'] }}</p><p>Locked after funding. Report an incorrect account before a transfer is sent.</p></div></details>@endif

    @if($agreement->funded_at && !$agreement->closed_at && $agreement->physicalReturn() && ($seller || $admin) && $returnRequest->workflow?->stage==='awaiting_collection')
    <details class="return-task" name="return-current-task" open><summary><h2>Schedule collection from the buyer</h2></summary><div class="return-disclosure-body"><p>Confirm availability with the buyer first. Logistics assigns the rider; there is no cash collection at the door.</p>
        <form method="post" action="{{ route('returns.agreement.update',$returnRequest) }}">@include('shared.returns.partials.form-identity',['action'=>'schedule'])
            <label>Pickup date and time (Philippine time)<input type="datetime-local" name="collection_at" value="{{ $agreement->collection_at?->copy()->timezone('Asia/Manila')->format('Y-m-d\TH:i') }}" required></label>
            @include('shared.returns.partials.confirm')<button type="submit">Schedule return pickup</button>
        </form>
    </div></details>@endif

    @include('shared.returns.partials.payment-receipts')

    @if(($buyer || $seller || $admin) && !$agreement->closed_at && ($agreement->funded_at || !$editable))
    <details><summary>Report a payment, parcel or account issue</summary><form method="post" action="{{ route('returns.agreement.update',$returnRequest) }}">@include('shared.returns.partials.form-identity',['action'=>'dispute'])
        <label>Issue details<textarea name="note" minlength="10" maxlength="1500" required></textarea></label>@include('shared.returns.partials.confirm')<button type="submit">Request payment or parcel help</button>
    </form></details>@endif
    @include('shared.returns.partials.direct-completion')
    <details class="return-disclosure"><summary><h2>Proposal conversation &amp; history</h2></summary><div class="return-disclosure-body"><ol>@foreach($agreement->events??[] as $event)<li><b>{{ $event['actor_label']??'System' }} · {{ ucwords(str_replace('_',' ',$event['action'])) }}</b> · Version {{ $event['version'] }} · {{ $event['at'] }}@if($event['note']??null)<p>{{ $event['note'] }}</p>@endif</li>@endforeach</ol></div></details>
</div>
<link rel="stylesheet" href="{{ asset('assets/shared/css/theme.css') }}">
<link rel="stylesheet" href="{{ asset('assets/shared/css/workflow-review.css') }}?v={{ filemtime(public_path('assets/shared/css/workflow-review.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/shared/css/return-settlements.css') }}?v={{ filemtime(public_path('assets/shared/css/return-settlements.css')) }}">
@endsection

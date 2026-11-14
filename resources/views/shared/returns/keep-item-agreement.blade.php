@extends($layout)
@section('title',($agreement->resolution==='partial_refund'?'Partial refund':'Refund only').' — buyer keeps item')
@section('content')
@php
    $flow=$returnRequest->workflow;
    $editable=!$agreement->closed_at && !$flow?->refund_sent_at;
    $partialRefund=$agreement->resolution==='partial_refund';
@endphp
<div class="return-center return-agreement return-flow-ui">
    @include('shared.returns.protected-transport')
    <header class="review-head"><div><p class="review-eyebrow">Return #{{ $returnRequest->id }} · Order #{{ $returnRequest->order_id }}</p><h1>{{ $partialRefund?'Partial refund':'Refund only' }} — buyer keeps item</h1></div></header>
<details class="return-disclosure return-introduction"><summary>How this works</summary><div class="return-disclosure-body"><p>Agree on the refund, verify the buyer's receiving account, then send the money directly from seller to buyer. The buyer confirms actual receipt.</p></div></details>
    <nav class="return-nav"><a class="review-action" href="{{ route('returns.center') }}">Return center</a><a class="review-action" href="{{ route('returns.guide') }}">How this works</a></nav>
    @if(session('success'))<p role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<p class="review-warning" role="alert">{{ $errors->first() }}</p>@endif
    <div class="return-case-context"><div class="return-heading"><h2>{{ $returnRequest->item?->product_name ?? 'Purchased item' }}<small>Proposal version {{ $agreement->version }}</small></h2><b>{{ ucwords(str_replace('_',' ',$agreement->status)) }}</b></div></div>

<details class="return-disclosure return-case-record"><summary>Agreement details &amp; amounts</summary><div class="return-disclosure-body">

        <p><b>Proposal:</b> {{ $agreement->reason_note }}</p><p>Buyer: {{ $agreement->buyer_accepted_at?'Agreed':'Awaiting agreement' }} · Seller: {{ $agreement->seller_accepted_at?'Agreed':'Awaiting agreement' }}.</p>
        <dl class="return-money"><div><dt>Item refund</dt><dd>{{ peso($agreement->merchandise_cents/100) }}</dd></div><div><dt>Shipping reimbursement</dt><dd>{{ peso($agreement->shipping_cents/100) }}</dd></div><div><dt>Buyer receives</dt><dd>{{ peso($agreement->net_refund_cents/100) }}</dd></div></dl>
        @if($partialRefund)<p><b>Agreed partial item refund:</b> {{ peso($agreement->merchandise_cents/100) }} of {{ peso($agreement->maximum_refund_cents/100) }} eligible item value. The seller sends the agreed buyer-receives amount shown above.</p>@endif
        <p>The buyer keeps the item. No pickup, return-delivery payment or Admin approval is needed for this refund.</p>
        @if($agreement->closed_at)<p class="review-status">Refund received and confirmed by the buyer.</p>@endif
    </div></details>
    @include('shared.returns.partials.propose')
    @if($buyer && $editable && $agreement->status==='proposed' && !$agreement->buyer_accepted_at && !$agreement->dispute_note)
    <details class="return-task" name="return-current-task" open><summary><h2>Review the seller's proposal</h2></summary><div class="return-disclosure-body"><form method="post" action="{{ route('returns.agreement.update',$returnRequest) }}">@include('shared.returns.partials.form-identity',['action'=>'accept'])@include('shared.returns.partials.confirm',['confirmation'=>'I agree to this refund amount and to keep the item.'])<button type="submit">Accept agreement</button></form></div></details>
    @endif
    @if(($buyer||$seller) && $editable)
    <details><summary>Suggest changes or decline proposal</summary><form method="post" action="{{ route('returns.agreement.update',$returnRequest) }}">@csrf<input type="hidden" name="version" value="{{ $agreement->version }}"><label>Response<select name="action"><option value="comment">Suggest changes</option><option value="decline">Decline proposal</option></select></label><label>Message<textarea name="note" minlength="10" maxlength="1500" required></textarea></label>@include('shared.returns.partials.confirm')<button type="submit">Send response</button></form></details>
    @endif
    @if($buyer && !$agreement->closed_at && (!$refundReceipt || $refundReceipt['status']==='declined'))
    <details class="return-task" name="return-current-task" open><summary><h2>Verify refund account</h2></summary><div class="return-disclosure-body"><p>The seller will see these details after you both agree. Enter the account that should receive your refund.</p><form method="post" action="{{ route('returns.agreement.update',$returnRequest) }}">@include('shared.returns.partials.form-identity',['action'=>'destination'])
        @foreach(['provider'=>'Bank / e-wallet','holder'=>'Account holder','account'=>'Account / mobile number'] as $key=>$label)<label>{{ $label }}<input name="{{ $key }}" value="{{ $destination[$key]??'' }}" maxlength="{{ $key==='holder'?150:100 }}" required autocomplete="off"></label>@endforeach
        @include('shared.returns.partials.confirm',['confirmation'=>'These are my correct receiving details. I agree to share them with this seller for the refund.'])<button type="submit">Verify refund account</button></form>
    </div></details>@endif
    @if($seller && $destination && $agreement->buyer_accepted_at && $agreement->seller_accepted_at && !$agreement->closed_at)
    <details class="return-task" name="return-current-task" {{ $returnRequest->status==='approved' && (!$refundReceipt || $refundReceipt['status']==='declined') ? 'open' : '' }}><summary><h2>Send the refund directly to the buyer</h2></summary><div class="return-disclosure-body"><p>{{ $destination['provider'] }} · {{ $destination['holder'] }}<br>{{ $destination['account'] }}</p><p class="return-payment-amount">Exact refund: <b>{{ peso($agreement->net_refund_cents/100) }}</b>.</p>
        @if($returnRequest->status==='approved' && (!$refundReceipt || $refundReceipt['status']==='declined'))
        <p>@if($refundReceipt)Check the buyer's message and your bank first. Correct the proof if the original transfer succeeded; do not send the same money twice.@else Send this exact amount using your bank or e-wallet, then upload the receipt below.@endif</p>
        <form method="post" action="{{ route('returns.action',$returnRequest) }}" enctype="multipart/form-data">@csrf<input type="hidden" name="action" value="refund">
            @if($refundReceipt)<input type="hidden" name="receipt_id" value="{{ $refundReceipt['id'] }}"><input type="hidden" name="receipt_version" value="{{ $refundReceipt['version'] }}">@endif
            <label>Exact amount sent<input name="amount" type="number" step="0.01" min="0.01" value="{{ $agreement->net_refund_cents/100 }}" required></label>
            <label>Actual transfer reference<input name="reference" minlength="4" maxlength="150" required></label><label>Seller refund receipt<input type="file" name="proof" accept="image/jpeg,image/png,image/webp" required></label>
            <label class="return-check"><input type="checkbox" name="confirm_transfer" value="1" required> I transferred the agreed amount to this buyer's account.</label><button type="submit">{{ $refundReceipt?'Submit corrected refund receipt':'Submit seller refund receipt' }}</button>
        </form>@else<p>Waiting for the buyer to review the submitted receipt.</p>@endif
    </div></details>@elseif($seller && !$agreement->closed_at)<details class="return-disclosure"><summary><h2>Waiting for buyer receiving details</h2></summary><div class="return-disclosure-body"><p>The buyer must accept the proposal and verify their refund account before you send money.</p></div></details>@endif
    @if($refundReceipt && ($buyer||$seller))
    <details class="return-task" name="return-current-task" {{ $buyer && !$agreement->closed_at ? 'open' : '' }}><summary><h2>{{ $flow?->buyer_confirmed_at?'Refund received':($refundReceipt['status']==='declined'?'Buyer reported a payment problem':'Review seller refund receipt') }}</h2></summary><div class="return-disclosure-body">
        <p>{{ peso($refundReceipt['amount_cents']/100) }} · {{ $refundReceipt['reference'] }} · Receipt version {{ $refundReceipt['version'] }}</p>
        <a class="review-action" data-image-viewer="document" href="{{ route('returns.proof',['returnRequest'=>$returnRequest,'receipt'=>$refundReceipt['id'],'revision'=>$refundReceipt['version']]) }}">View seller refund receipt</a>
        @if($refundReceipt['buyer_note'])<p class="review-warning">Buyer: {{ $refundReceipt['buyer_note'] }}</p>@endif
        @if($buyer && !$agreement->closed_at)
        <p>A screenshot alone is not proof that the money reached your bank. Confirm after checking your actual balance.</p>
        <form method="post" action="{{ route('returns.action',$returnRequest) }}">@csrf<input type="hidden" name="action" value="confirm_refund"><input type="hidden" name="receipt_id" value="{{ $refundReceipt['id'] }}"><input type="hidden" name="receipt_version" value="{{ $refundReceipt['version'] }}">@include('shared.returns.partials.confirm',['confirmation'=>'I received the exact agreed refund in my account.'])<button type="submit">Confirm refund received</button></form>
        <details><summary>Decline receipt / payment not received</summary><form method="post" action="{{ route('returns.agreement.update',$returnRequest) }}">@include('shared.returns.partials.form-identity',['action'=>'decline'])<input type="hidden" name="receipt_id" value="{{ $refundReceipt['id'] }}"><input type="hidden" name="receipt_version" value="{{ $refundReceipt['version'] }}"><label>What went wrong? Suggest a correction<textarea name="note" minlength="10" maxlength="1500" required placeholder="The money has not reached my account. Please check the transfer status."></textarea></label>@include('shared.returns.partials.confirm')<button type="submit">Send payment problem to seller</button></form></details>
        @endif
    </div></details>@endif
    <details class="return-disclosure"><summary><h2>Refund conversation &amp; history</h2></summary><div class="return-disclosure-body"><ol>@foreach($agreement->events??[] as $event)<li><b>{{ $event['actor_label']??'System' }} · {{ ucwords(str_replace('_',' ',$event['action'])) }}</b> · {{ $event['at'] }}@if($event['note']??null)<p>{{ $event['note'] }}</p>@endif</li>@endforeach</ol>
        @if(($buyer||$seller) && $refundReceipt && !$agreement->closed_at)<form method="post" action="{{ route('returns.agreement.update',$returnRequest) }}">@include('shared.returns.partials.form-identity',['action'=>'comment'])<label>Message or suggested correction<textarea name="note" minlength="10" maxlength="1500" required></textarea></label>@include('shared.returns.partials.confirm')<button type="submit">Send message</button></form>@endif
    </div></details>
</div>
<link rel="stylesheet" href="{{ asset('assets/shared/css/theme.css') }}">
<link rel="stylesheet" href="{{ asset('assets/shared/css/workflow-review.css') }}"><link rel="stylesheet" href="{{ asset('assets/shared/css/return-settlements.css') }}">
@endsection

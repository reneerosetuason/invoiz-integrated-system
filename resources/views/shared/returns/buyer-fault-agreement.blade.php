@extends($layout)
@section('title', 'Return & direct refund')
@section('content')
@php
    $editable=!$agreement->collection_authorized_at && !$agreement->funded_at && !$agreement->closed_at && !$agreement->payments->whereIn('status',['submitted','verified'])->count();
    $sellerFault=$agreement->sellerFaultDirect();
    $flow=$returnRequest->workflow;
    $pendingFee=$agreement->payments->first(fn($p)=>$p->purpose==='return_budget' && $p->status==='submitted');
    $lastIssue=collect($agreement->events??[])->where('action','dispute')->last();
@endphp
<div class="return-center return-agreement return-flow-ui">
    @include('shared.returns.protected-transport')
    <header class="review-head"><div><p class="review-eyebrow">Return #{{ $returnRequest->id }} · Proposal version {{ $agreement->version }}</p>
        <h1>Return &amp; direct refund</h1></div></header>
<details class="return-disclosure return-introduction"><summary>How this works</summary><div class="return-disclosure-body"><p>@if($agreement->platform_subsidy_cents)The platform funds the shown transport and eligible fee support. The seller sends your agreed refund directly; seller fee reimbursement is separate and follows buyer confirmation.@elseif($sellerFault)The seller pays all return-delivery costs separately and refunds your full eligible paid amount directly, with no deductions. No admin approval of the customer refund is needed.@else Buyer and seller agree on the deductions. The seller refunds the buyer directly and funds only the new return delivery through escrow.@endif</p></div></details>
    <nav class="return-nav"><a class="review-action" href="{{ route('returns.center') }}">Return center</a><a class="review-action" href="{{ route('returns.guide') }}">How this works</a>
        @if($admin)<a class="review-action" href="{{ url('/admin/payments#escrow-remittances') }}">Manage Payments</a>@endif</nav>
    @if(session('success'))<p role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<p class="review-warning" role="alert">{{ $errors->first() }}</p>@endif
    <div class="return-case-context"><div class="return-heading"><h2>{{ $returnRequest->item?->product_name }}<small>{{ $returnRequest->quantity }} item(s) · {{ $sellerFault?'Seller':'Buyer' }} responsibility</small></h2><b>{{ ucwords(str_replace('_',' ',$agreement->status)) }}</b></div></div>
@if($agreement->dispute_note)<p class="review-warning">Reported issue: {{ $agreement->dispute_note }}. Discuss this with the other party; the reporter can mark it resolved.</p>@endif
<details class="return-disclosure return-case-record"><summary>Agreement details &amp; amounts</summary><div class="return-disclosure-body">

        <p><b>Seller proposal:</b> {{ $agreement->reason_note }}</p>
        <p>Buyer: {{ $agreement->buyer_accepted_at?'Agreed':'Awaiting agreement' }} · Seller: {{ $agreement->seller_accepted_at?'Agreed':'Awaiting agreement' }}</p>
        <dl class="return-money">
            <div><dt>Eligible item refund</dt><dd>{{ peso($agreement->merchandise_cents/100) }}</dd></div>
            @if($sellerFault)
            <div><dt>Original paid shipping reimbursement</dt><dd>{{ peso($agreement->shipping_cents/100) }}</dd></div>
            <div><dt>Return delivery paid separately by seller</dt><dd>{{ peso($agreement->payerBudgetCents()/100) }}</dd></div>
            @else
            <div><dt>Less original seller-funded costs</dt><dd>− {{ peso($agreement->compensation_cents/100) }}</dd></div>
            <div><dt>Less new return delivery</dt><dd>− {{ peso($agreement->payerBudgetCents()/100) }}</dd></div>
            @endif
            <div><dt>Buyer receives directly from seller</dt><dd>{{ peso($agreement->net_refund_cents/100) }}</dd></div>
        </dl>
        <p>{{ $agreement->deduction_note }}</p>
        @if($sellerFault)<p>The buyer receives the full eligible item amount plus the original paid shipping for this returned quantity. No seller compensation or return-delivery fee is deducted.</p>
        @else
        <p>You do not pay a separate return fee. The seller funds the return trip using the agreed deduction.</p>
        <details><summary>How original seller compensation is calculated</summary>
            <p>These are recorded original order costs for this returned quantity. Original buyer-paid shipping is credited so it is not charged twice. The seller retains this cost recovery; it is not another escrow payout.</p>
            <table class="return-fees"><thead><tr><th scope="col">Original cost</th><th scope="col">Allocated amount</th></tr></thead><tbody>
                @foreach($agreement->cost_snapshot['shares_cents']??[] as $role=>$amount)<tr><td>{{ ucwords(str_replace('_',' ',$role)) }}</td><td>{{ peso($amount/100) }}</td></tr>@endforeach
                <tr><td>Less original buyer-paid shipping credit</td><td>− {{ peso(($agreement->cost_snapshot['buyer_shipping_credit_cents']??0)/100) }}</td></tr>
            </tbody><tfoot><tr><th scope="row">Seller-funded costs retained</th><td>{{ peso($agreement->compensation_cents/100) }}</td></tr></tfoot></table>
        </details>
        @endif

    </div></details>
    <details class="return-disclosure"><summary><h2>New return delivery · {{ peso($agreement->payerBudgetCents()/100) }}</h2></summary><div class="return-disclosure-body">
        <p>{{ $feePreview['collection_hub_name']??'Buyer-side hub' }} → {{ $feePreview['seller_hub_name']??'Seller-side hub' }}. Prices are taken automatically from Commission settings when this proposal is sent.</p>
        <table class="return-fees"><thead><tr><th scope="col">Return service</th><th scope="col">Amount</th></tr></thead><tbody>
            @foreach($agreement->allocations->whereIn('role',\App\Models\ReturnAllocation::DELIVERY_ROLES) as $line)<tr><td>{{ ucwords(str_replace('_',' ',$line->role)) }}</td><td>{{ peso($line->amount_cents/100) }}</td></tr>@endforeach
        </tbody></table><p>Only services on this route are charged. The return driver belongs to the buyer-side collection hub's company. There is no new admin commission.</p>
    </div></details>
    @include('shared.returns.partials.propose')
    @if($buyer && $editable && $agreement->status==='proposed' && !$agreement->buyer_accepted_at && !$agreement->dispute_note)
    <details class="return-task" name="return-current-task" open><summary><h2>Review and agree</h2></summary><div class="return-disclosure-body"><p>You will receive <strong class="return-action-amount">{{ peso($agreement->net_refund_cents/100) }}</strong> {{ $sellerFault?'in full, with no deductions':'after the agreed deductions' }}. You do not pay a separate return fee. The seller deposits {{ peso($agreement->payerBudgetCents()/100) }} for the return trip.</p>
        <form method="post" action="{{ route('returns.agreement.update',$returnRequest) }}">@include('shared.returns.partials.form-identity',['action'=>'accept'])
            @include('shared.returns.partials.confirm',['confirmation'=>$sellerFault?'I agree to the full refund, physical return and sharing my receiving details with this seller.':'I agree to these deductions, the net refund and sharing my refund receiving details with this seller.'])<button type="submit">Accept agreement</button>
        </form>
    </div></details>@endif
    @if(($buyer||$seller) && $editable)
    <details><summary>Suggest changes or decline</summary><form method="post" action="{{ route('returns.agreement.update',$returnRequest) }}">@csrf<input type="hidden" name="version" value="{{ $agreement->version }}">
        <label>Response<select name="action"><option value="comment">Suggest a change</option><option value="decline">Decline proposal</option></select></label>
        <label>Comment or suggestion<textarea name="note" minlength="10" maxlength="1500" required></textarea></label>@include('shared.returns.partials.confirm')<button type="submit">Send response</button>
    </form></details>@endif
    @if($buyer && !$flow?->refund_sent_at && !$agreement->closed_at)
    <details class="return-task" name="return-current-task" open><summary><h2>Your refund receiving account</h2></summary><div class="return-disclosure-body"><p>You and this seller can see these details after mutual agreement. Enter only receiving details, never a PIN, password or OTP.</p>
        <form method="post" action="{{ route('returns.agreement.update',$returnRequest) }}">@include('shared.returns.partials.form-identity',['action'=>'destination'])
            @foreach(['provider'=>'Bank / e-wallet','holder'=>'Account holder','account'=>'Account / e-wallet number'] as $key=>$label)<label>{{ $label }}<input name="{{ $key }}" value="{{ $destination[$key]??'' }}" maxlength="{{ $key==='holder'?150:100 }}" required autocomplete="off"></label>@endforeach
            @include('shared.returns.partials.confirm',['confirmation'=>'These are my correct receiving details. The seller may use them for this refund.'])<button type="submit">Save refund account</button>
        </form>
    </div></details>@endif
    @if($agreement->agreed() && !$agreement->closed_at)
    <details class="return-task" name="return-current-task" {{ $seller && !$pendingFee && $budgetOutstanding>0 ? 'open' : '' }}><summary><h2>Seller's delivery escrow deposit</h2></summary><div class="return-disclosure-body"><p>The seller transfers only {{ peso($agreement->payerBudgetCents()/100) }} to the platform account. The item refund is sent directly to the buyer after return receipt.</p>
        @if($pendingFee)<p class="review-status">Receipt submitted. Collection is authorized once the receiving account is saved. Bank verification is still pending and payouts remain blocked until funds are confirmed.</p>
        @elseif($budgetOutstanding===0)<p class="review-status">Delivery funds verified. Service payouts still require completed work and recipient acceptance.</p>
        @elseif($seller)@include('shared.returns.partials.payment-upload',['paymentPurpose'=>'return_budget','paymentCents'=>$budgetOutstanding,'receiptLabel'=>'Return-delivery receipt screenshot','uploadButton'=>'Submit delivery deposit receipt'])
        @else<p>Waiting for the seller to deposit the delivery fee and upload its receipt.</p>@endif
    </div></details>@endif
    @if($seller && $agreement->collectionReady() && !$agreement->closed_at && $flow?->stage==='awaiting_collection')
    <details class="return-task" name="return-current-task" open><summary><h2>Schedule pickup from the buyer</h2></summary><div class="return-disclosure-body"><p>Agree on availability with the buyer. The collection hub assigns its rider; no cash is collected at the door.</p>
        <form method="post" action="{{ route('returns.agreement.update',$returnRequest) }}">@include('shared.returns.partials.form-identity',['action'=>'schedule'])
            <label>Pickup date and time (Philippine time)<input type="datetime-local" name="collection_at" value="{{ $agreement->collection_at?->copy()->timezone('Asia/Manila')->format('Y-m-d\TH:i') }}" required></label>@include('shared.returns.partials.confirm')<button type="submit">Schedule return pickup</button>
        </form>
    </div></details>@endif
    @if($seller && $destination && $agreement->buyer_accepted_at && $agreement->seller_accepted_at)
    <details class="return-task" name="return-current-task" {{ $returnRequest->status==='item_received' && !$flow?->refund_sent_at && $agreement->collectionReady() ? 'open' : '' }}><summary><h2>{{ $sellerFault?'Send the full refund directly to the buyer':'Send the net refund directly to the buyer' }}</h2></summary><div class="return-disclosure-body"><p>{{ $destination['provider'] }} · {{ $destination['holder'] }}<br>{{ $destination['account'] }}</p>
        <p class="return-payment-amount">Exact amount: <b>{{ peso($agreement->net_refund_cents/100) }}</b>. Confirm physical receipt of the returned item in the Return center first.</p>
        @if($returnRequest->status==='item_received' && !$flow?->refund_sent_at && $agreement->collectionReady())
        <form method="post" action="{{ route('returns.action',$returnRequest) }}" enctype="multipart/form-data">@csrf<input type="hidden" name="action" value="refund">
            <label>Actual outgoing transfer reference<input name="reference" minlength="4" maxlength="150" required></label><label>Refund transfer receipt<input type="file" name="proof" accept="image/jpeg,image/png,image/webp" required></label>
            <label class="return-check"><input type="checkbox" name="confirm_transfer" value="1" required> I sent the exact agreed refund to this buyer account.</label><button type="submit">Record direct refund sent</button>
        </form>@endif
    </div></details>@endif
    @if($flow?->refund_sent_at && ($buyer||$seller))
    <details class="return-task" name="return-current-task" {{ $buyer && !$agreement->closed_at ? 'open' : '' }}><summary><h2>{{ $flow->buyer_confirmed_at?'Refund received':'Seller recorded refund sent' }}</h2></summary><div class="return-disclosure-body"><p>{{ peso($agreement->net_refund_cents/100) }} · {{ $flow->refund_reference }}. A receipt alone is not confirmation that the buyer received money.</p>
        <a class="review-action" data-image-viewer="document" href="{{ route('returns.proof',$returnRequest) }}">View refund receipt</a>
        @if($buyer && !$flow->buyer_confirmed_at)<form method="post" action="{{ route('returns.action',$returnRequest) }}">@csrf<input type="hidden" name="action" value="confirm_refund"><label class="return-check"><input type="checkbox" name="confirm_transfer" value="1" required> I checked my actual account and received the refund.</label><button type="submit">Confirm refund received</button></form>@endif
    </div></details>@endif
    @if(!$admin)@include('shared.returns.partials.payment-receipts')@endif
    @if(($buyer||$seller) && !$agreement->closed_at && !$editable)
    <details><summary>Report a payment or parcel issue</summary><form method="post" action="{{ route('returns.agreement.update',$returnRequest) }}">@include('shared.returns.partials.form-identity',['action'=>'dispute'])<label>Message to the other party<textarea name="note" minlength="10" maxlength="1500" required></textarea></label>@include('shared.returns.partials.confirm')<button type="submit">Send issue to other party</button></form></details>@endif
    @if($agreement->dispute_note && (int)($lastIssue['actor_id']??0)===(int)$user->id)
    <details><summary>Mark your reported issue resolved</summary><form method="post" action="{{ route('returns.agreement.update',$returnRequest) }}">@include('shared.returns.partials.form-identity',['action'=>'resolve_issue'])<label>Agreed resolution<textarea name="note" minlength="10" maxlength="1500" required></textarea></label>@include('shared.returns.partials.confirm')<button type="submit">Resolve my reported issue</button></form></details>@endif
    <details class="return-disclosure"><summary><h2>Conversation &amp; history</h2></summary><div class="return-disclosure-body"><ol>@foreach($agreement->events??[] as $event)<li>{{ $event['actor_label']??'System' }} · {{ ucwords(str_replace('_',' ',$event['action'])) }} · Version {{ $event['version'] }}@if($event['note']??null)<p>{{ $event['note'] }}</p>@endif</li>@endforeach</ol></div></details>
</div>
<link rel="stylesheet" href="{{ asset('assets/shared/css/theme.css') }}">
<link rel="stylesheet" href="{{ asset('assets/shared/css/workflow-review.css') }}">
<link rel="stylesheet" href="{{ asset('assets/shared/css/return-settlements.css') }}">
@endsection

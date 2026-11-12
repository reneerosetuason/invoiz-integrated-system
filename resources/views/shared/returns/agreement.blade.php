@extends($layout)
@section('title', 'Return agreement & funding')
@section('content')
<div class="return-center return-agreement return-flow-ui">
    <header class="review-head"><div><p class="review-eyebrow">Return #{{ $returnRequest->id }} · Order #{{ $returnRequest->order_id }}</p>
        <h1>Return agreement &amp; funding</h1></div></header>
<details class="return-disclosure return-introduction"><summary>How this works</summary><div class="return-disclosure-body"><p>Decide the resolution, agree on charges, then fund the refund and any new transport work.</p></div></details>
    <nav class="return-nav"><a class="review-action" href="{{ route('returns.center') }}">Return center</a><a class="review-action" href="{{ route('returns.remittances') }}">Remittances</a><a class="review-action" href="{{ route('returns.guide') }}">How this works</a></nav>
    @if(session('success'))<p role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<div class="review-warning" role="alert">{{ $errors->first() }}</div>@endif
    <div class="return-case-context"><div class="return-heading"><h2>{{ $returnRequest->item?->product_name ?? 'Purchased item' }}<small>Quantity: {{ $returnRequest->quantity }} · Agreement version {{ $agreement->version }}</small></h2><b>{{ ucwords(str_replace('_', ' ', $agreement->status)) }}</b></div></div>
@if($agreement->dispute_note)<p class="review-warning"><b>Disputed:</b> {{ $agreement->dispute_note }}. Collection and refund cannot proceed until admin reviews this.</p>@endif
<details class="return-disclosure return-case-record"><summary>Agreement details &amp; amounts</summary><div class="return-disclosure-body">

        <p>Reason: {{ ucwords(str_replace('_', ' ', $returnRequest->reason)) }}. {{ $returnRequest->reason_details }}</p>
        @if($returnRequest->item?->variant_label)<p><b>Purchased variation:</b> {{ $returnRequest->item->variant_label }}</p>@endif
        @if($agreement->replacement_description)<p><b>Agreed replacement:</b> {{ $agreement->replacement_description }}</p>@endif
        <p><b>Resolution:</b> {{ ucwords(str_replace('_', ' ', $agreement->resolution)) }} · <b>Proposed responsibility:</b> {{ ucfirst($agreement->fault) }} · <b>Transport payer:</b> {{ ucfirst($agreement->payer) }}</p>
        @if(!$agreement->physicalReturn())<p class="review-warning">The buyer keeps the item. Do not confirm a physical return, restore stock, or create return transport earnings.</p>@endif
        <dl class="return-money">
            <div><dt>Eligible item value</dt><dd>{{ peso($agreement->maximum_refund_cents / 100) }}</dd></div>
            <div><dt>Agreed item refund</dt><dd>{{ peso($agreement->merchandise_cents / 100) }}</dd></div>
            <div><dt>Original shipping reimbursement</dt><dd>{{ peso($agreement->shipping_cents / 100) }}</dd></div>
            <div><dt>New transport budget</dt><dd>{{ $agreement->physicalReturn() && !$agreement->quoted_at ? 'Awaiting transport quote' : peso($agreement->transport_cents / 100) }}</dd></div>
            <div><dt>Justified seller compensation</dt><dd>{{ peso($agreement->compensation_cents / 100) }}</dd></div>
            <div><dt>{{ $agreement->physicalReturn() && !$agreement->quoted_at ? 'Item refund before return fees' : 'Buyer receives' }}</dt><dd>{{ peso($agreement->net_refund_cents / 100) }}</dd></div>
        </dl>
        @if($agreement->physicalReturn() && !$agreement->quoted_at)
            <p>Funding will be calculated after the route and fees are quoted. A missing quote does not mean a free return.</p>
        @elseif($agreement->payer === 'seller' && $agreement->funding_preference === 'auto')
            <p>Funding: use the seller's available verified escrow for this order first. The seller deposits only the remaining shortfall. The buyer refund stays protected.</p>
        @else
            <p>Funding: {{ $agreement->funding_method === 'deduct_refund' ? 'Agreed deduction from the funded refund. Do not pay the same return fee again.' : 'Separate verified payment to platform escrow.' }}</p>
        @endif
        @if($agreement->quoted_at && $agreement->budgetCents() > 0)
            <p><b>Return fees secured from existing escrow:</b> {{ peso(($agreement->reserved_cents + $agreement->seller_reserved_cents) / 100) }} · <b>Remaining return payment:</b> {{ peso($budgetOutstanding / 100) }}.</p>
            @if(!$agreement->agreed())<p>Escrow is reserved only after both parties accept and admin reviews the complete terms.</p>@endif
        @endif
        <p>Buyer agreement: {{ $agreement->buyer_accepted_at ? 'Accepted' : 'Pending' }} · Seller agreement: {{ $agreement->seller_accepted_at ? 'Accepted / upheld by admin' : 'Pending' }} · Admin responsibility review: {{ $agreement->admin_decided_at ? 'Recorded' : 'Pending' }}</p>
        @if($agreement->refund_reserved_cents > 0)<p><b>Buyer refund secured in escrow:</b> {{ peso($agreement->refund_reserved_cents / 100) }}. This hold is released when the actual refund transfer is recorded.</p>@endif
        @if($agreement->reason_note)<p><b>Proposal:</b> {{ $agreement->reason_note }}</p>@endif
        @if($agreement->decision_note)<p><b>Admin decision:</b> {{ $agreement->decision_note }}</p>@endif

        @if($agreement->response_due_at && !$agreement->agreed() && !$agreement->closed_at)<p>Response requested by {{ $agreement->response_due_at->format('M j, Y g:i a') }}. {{ $agreement->response_due_at->isPast() ? 'Overdue — request admin review. No automatic rejection or payment.' : '' }}</p>@endif
        @if($agreement->collection_at)<p><b>Scheduled collection:</b> {{ $agreement->collection_at->copy()->timezone('Asia/Manila')->format('M j, Y g:i a') }} PHT</p>@endif
        @if($agreement->allocations->isNotEmpty())
            <details><summary>View all quoted services and amounts</summary>
                @foreach($agreement->allocations as $line)<p>{{ ucwords(str_replace('_', ' ', $line->role)) }} · {{ peso($line->amount_cents / 100) }} · {{ ucwords(str_replace('_', ' ', $line->status)) }}@if($line->center_id) · Hub #{{ $line->center_id }}@endif</p>@endforeach
            </details>
        @endif
    </div></details>

    @if(!$agreement->closed_at)
    @if($admin)
    <details class="return-disclosure"><summary><h2>What admin needs to do</h2></summary><div class="return-disclosure-body">
        <p><b>Proposal:</b> normally sent by the seller. Use the admin override below only to correct or mediate terms.</p>
        <p><b>Transport quote:</b> logistics or admin identifies the actual route and service fees. No buyer can accept an incomplete physical-return quote.</p>
        <p><b>Responsibility review:</b> review the evidence and any disagreement. This is separate from verifying money in the platform account.</p>
        <p><b>Funding:</b> agreed buyer deductions and available seller escrow are reserved automatically. Verify any shortfall deposit, then confirm funding to enable the resolution.</p>
    </div></details>
    @endif
    @if(($seller || $admin) && !$agreement->funded_at)
    @if($admin)<details><summary>Change proposed terms — admin override</summary><p>Changing the terms requires fresh acceptance. Existing submitted payments must be resolved first.</p>@endif
    <article><h2>1. Propose a resolution</h2><p>Wrong size ordered is different from wrong size delivered. Compare the purchased variation and evidence. Lost profit is not an automatic charge.</p>
        <form method="post" action="{{ route('returns.agreement.update', $returnRequest) }}">
            @include('shared.returns.partials.form-identity', ['action' => 'propose'])
            <label>Responsibility<select name="fault" required>@foreach(['seller'=>'Seller fault', 'buyer'=>'Buyer selected incorrectly / change of mind', 'logistics'=>'Logistics damage or loss'] as $value=>$label)<option value="{{ $value }}" @selected($agreement->fault === $value)>{{ $label }}</option>@endforeach</select></label>
            <label>Resolution<select name="resolution" required>@foreach(['return_refund'=>'Return and refund', 'refund_only'=>'Refund only — buyer keeps item', 'partial_refund'=>'Partial refund — buyer keeps item'] as $value=>$label)<option value="{{ $value }}" @selected($agreement->resolution === $value)>{{ $label }}</option>@endforeach</select></label>
            <label>Who pays new transport?<select name="payer"><option value="seller" @selected($agreement->payer === 'seller')>Seller (including sponsored free returns)</option><option value="buyer" @selected($agreement->payer === 'buyer')>Buyer — buyer-fault cases only</option>@if($admin)<option value="platform" @selected($agreement->payer === 'platform')>Platform-funded resolution</option>@endif</select></label>
            <label>How to fund return fees<select name="funding_preference"><option value="auto" @selected(($agreement->funding_preference ?? 'auto') === 'auto')>Automatic — use available verified escrow first</option><option value="deposit" @selected($agreement->funding_preference === 'deposit')>Pay return fees separately to platform escrow</option><option value="deduct_refund" @selected($agreement->funding_preference === 'deduct_refund')>Require an agreed buyer-refund deduction (buyer payer only)</option></select></label>
            <p>Automatic funding uses an agreed buyer-refund deduction when verified funds cover it. Otherwise the buyer pays separately. For seller-funded returns, available seller escrow is used first and only the shortfall needs a deposit. New terms must be accepted before any reservation.</p>
            <label>Item refund amount<input name="merchandise_amount" type="number" min="0" step="0.01" value="{{ $agreement->merchandise_cents / 100 }}" required></label>
            <label>Original shipping reimbursement<input name="shipping_amount" type="number" min="0" step="0.01" value="{{ $agreement->shipping_cents / 100 }}"></label>
            <label>Justified seller compensation (normally zero)<input name="compensation_amount" type="number" min="0" step="0.01" value="{{ $agreement->compensation_cents / 100 }}"></label>
            <label>Reason, evidence review and charge explanation<textarea name="note" minlength="10" maxlength="1500" required>{{ $agreement->reason_note }}</textarea></label>
            @include('shared.returns.partials.confirm')
            <button type="submit">Send resolution proposal</button>
        </form>
    </article>
    @if($admin)</details>@endif
    @endif

    @if(($admin || ($user->isLogisticsStaff() && \App\Support\LogisticsAccess::isCenterStaff($user, (int) $agreement->collection_center_id))) && $agreement->physicalReturn() && $agreement->status !== 'draft' && !$agreement->funded_at)
    <details class="return-task" name="return-current-task" @if(!$agreement->quoted_at) open @endif><summary>{{ $agreement->quoted_at ? 'Review or revise the transport quote' : 'Complete the return route and fees' }}</summary>
    <article><h2>2. Quote the actual return route</h2><p>Only include services that will be performed. Same-hub returns cannot charge inter-hub transport or a second handling hub. A changed quote needs fresh acceptance.</p>
        <form method="post" action="{{ route('returns.agreement.update', $returnRequest) }}">
            @include('shared.returns.partials.form-identity', ['action' => 'quote'])
            @foreach(['collection_center_id'=>'Buyer-side collection hub', 'seller_center_id'=>'Seller-side receiving hub'] as $key=>$label)
                <label>{{ $label }}<select name="{{ $key }}" required><option value="">Choose an active hub</option>@foreach($centers as $hub)<option value="{{ $hub->id }}" @selected((int)$agreement->$key === (int)$hub->id)>{{ $hub->name }} · {{ $hub->city }}</option>@endforeach</select></label>
            @endforeach
            @foreach(\App\Models\ReturnAllocation::ROLES as $index=>$role)
                <input type="hidden" name="allocations[{{ $index }}][role]" value="{{ $role }}">
                <label>{{ ucwords(str_replace('_', ' ', $role)) }} fee (zero to omit)<input type="number" name="allocations[{{ $index }}][amount]" min="0" step="0.01" value="{{ ($agreement->allocations->firstWhere('role', $role)?->amount_cents ?? 0) / 100 }}"></label>
            @endforeach
            @include('shared.returns.partials.confirm')
            <button type="submit">Save transport quote</button>
        </form>
    </article>
    </details>
    @endif

    @if((($buyer && !$agreement->buyer_accepted_at) || ($seller && !$agreement->seller_accepted_at)) && $agreement->quoted_at && !$agreement->dispute_note && !$agreement->funded_at)
    <details class="return-task" name="return-current-task" open><summary><h2>Review and agree</h2></summary><div class="return-disclosure-body"><p>Read the refund, fees, payer and item-return requirement above. Accepting a keep-item resolution does not mean you physically returned anything.</p>
        <form method="post" action="{{ route('returns.agreement.update', $returnRequest) }}">
            @include('shared.returns.partials.form-identity', ['action' => 'accept'])
            @include('shared.returns.partials.confirm', ['confirmation' => 'I agree to this resolution and the complete financial breakdown.'])
            <button type="submit">Accept agreement</button>
        </form>
    </div></details>
    @endif

    @if($admin && $agreement->quoted_at && (!$agreement->funded_at || $agreement->dispute_note) && (!$agreement->admin_decided_at || $agreement->dispute_note))
    <details class="return-task" name="return-current-task" open><summary><h2>Admin responsibility decision</h2></summary><div class="return-disclosure-body"><p>Review evidence and the purchased variation. Upholding a seller-fault remedy can replace a seller's refusal, but buyer agreement to charges is still required.</p>
        <form method="post" action="{{ route('returns.agreement.update', $returnRequest) }}">@include('shared.returns.partials.form-identity', ['action' => 'decide'])
            <label>Decision and supporting reasons<textarea name="note" minlength="10" maxlength="1500" required></textarea></label>
            @include('shared.returns.partials.confirm')<button type="submit">Record admin decision</button>
        </form>
    </div></details>
    @endif
    @if($admin && $agreement->dispute_note && !$agreement->quoted_at)
    <p class="review-warning">This case is disputed and still has no transport quote. Complete the route and fees before recording the final responsibility decision. Any revised terms need fresh acceptance.</p>
    @endif

    @if($buyer || $seller || $admin)
    <details><summary>Disagree or request admin review</summary><form method="post" action="{{ route('returns.agreement.update', $returnRequest) }}">@include('shared.returns.partials.form-identity', ['action' => 'dispute'])
        <label>Dispute / escalation reason<textarea name="note" minlength="10" maxlength="1500" required></textarea></label>
        @include('shared.returns.partials.confirm')<button type="submit">Request admin review</button>
    </form></details>
    @endif

    @if($buyer && $agreement->resolution !== 'exchange' && (!$agreement->funded_at || !$agreement->destination_verified_at))
    <details class="return-task" name="return-current-task" open><summary><h2>Private refund receiving account</h2></summary><div class="return-disclosure-body"><p>Only you and admin can view the complete account. No PIN, password or OTP is needed.</p>
        <form method="post" action="{{ route('returns.agreement.update', $returnRequest) }}">@include('shared.returns.partials.form-identity', ['action' => 'destination'])
            <label>Bank / e-wallet<input name="provider" maxlength="100" value="{{ $destination['provider'] ?? '' }}" required autocomplete="off"></label>
            <label>Account holder<input name="holder" maxlength="150" value="{{ $destination['holder'] ?? '' }}" required autocomplete="off"></label>
            <label>Account number / e-wallet number<input name="account" minlength="4" maxlength="100" value="{{ $destination['account'] ?? '' }}" required autocomplete="off"></label>
            @include('shared.returns.partials.confirm', ['confirmation' => 'I checked that these are my correct receiving details.'])<button type="submit">Verify refund account</button>
        </form>
    </div></details>
    @endif
    @if($admin && $destination)<details class="return-disclosure"><summary><h2>Verified buyer receiving details</h2></summary><div class="return-disclosure-body"><p>{{ $destination['provider'] }} · {{ $destination['holder'] }}<br>{{ $destination['account'] }}</p><p>Transfer the exact net refund; record it in the Return center after physical inspection or the agreed return waiver.</p></div></details>@endif
    @if($buyer && $agreement->funded_at && $agreement->destination_verified_at)<details class="return-disclosure"><summary><h2>Your verified refund account</h2></summary><div class="return-disclosure-body"><p>{{ $destination['provider'] }} · {{ $destination['holder'] }}<br>{{ $destination['account'] }}</p><p>Locked after funding. If a correction is needed, request admin review before any transfer.</p></div></details>@endif
    @if($admin && $agreement->funded_at && !$returnRequest->workflow?->refund_sent_at)<details><summary>Request refund account correction</summary><form method="post" action="{{ route('returns.agreement.update', $returnRequest) }}">@include('shared.returns.partials.form-identity', ['action'=>'unlock_destination'])
        <label>Reason for account correction<textarea name="note" minlength="10" maxlength="1500" required></textarea></label>
        @include('shared.returns.partials.confirm')<button type="submit">Unlock refund account for correction</button>
    </form></details>@endif
    @if($admin && $agreement->funded_at && $agreement->net_refund_cents == 0 && $agreement->resolution !== 'exchange' && ($returnRequest->status === 'item_received' || (!$agreement->physicalReturn() && $returnRequest->status === 'approved')))
    <details class="return-task" name="return-current-task" open><summary><h2>No cash refund is due</h2></summary><div class="return-disclosure-body"><p>The accepted resolution has zero cash value. Close it explicitly without recording a fictitious bank transfer.</p><form method="post" action="{{ route('returns.agreement.update', $returnRequest) }}">@include('shared.returns.partials.form-identity', ['action'=>'complete_no_cash'])
        @include('shared.returns.partials.confirm')<button type="submit">Close zero-cash resolution</button>
    </form></div></details>
    @endif

    @if($agreement->agreed() && !$agreement->funded_at)
    @php($paymentDue = $fundingPlan['payment_due_cents'])
    <details class="return-task" name="return-current-task" {{ !$agreement->payments->contains('status','submitted') && ($admin || ($seller && ($agreement->payer==='seller' || $funding['shortfall']>0)) || ($buyer && $agreement->payer==='buyer')) ? 'open' : '' }}><summary><h2>Secure the funds</h2></summary><div class="return-disclosure-body">
        <p>Outstanding return budget: <b>{{ peso($budgetOutstanding / 100) }}</b>. Additional money needed to secure the buyer refund: <b>{{ peso($funding['shortfall']) }}</b>.</p>
        <p>Buyer fee deduction reserved: <b>{{ peso($agreement->reserved_cents / 100) }}</b> · Seller escrow reserved for return fees: <b>{{ peso($agreement->seller_reserved_cents / 100) }}</b>.</p>
        @if($fundingPlan['prospective_reservation_cents'] > 0)<p>Verified escrow available to reserve when funding is confirmed: <b>{{ peso($fundingPlan['prospective_reservation_cents'] / 100) }}</b>. No separate payment is needed for that amount.</p>@endif
        @if($agreement->payer === 'seller' && $agreement->funding_preference === 'auto')<p>Only unreleased seller funds from this order can be reserved. Original delivery earnings and the full buyer refund are protected. Separate return-fee payment needed after available escrow: <b>{{ peso($paymentDue / 100) }}</b>.</p>@endif
        @if($funding['funded'] <= 0)<p class="review-warning">The original payment is not reconciled yet. Admin must reconcile collected COD or the original GCash escrow before refund recovery, deductions or collection can unlock.</p>@endif
        @php($canPay = ($agreement->payer === 'buyer' && $buyer) || ($agreement->payer === 'seller' && $seller) || ($agreement->payer === 'platform' && $admin))
        @if(($canPay && $agreement->funding_method === 'deposit' && $paymentDue > 0) || (($seller || $admin) && $funding['shortfall'] > 0))
        @if($settings)<p>Platform receiving account: <b>{{ $settings->account_name }}</b><br>{{ $settings->account_details }}</p>
            @if($settings->qr_path)<a class="review-action" data-image-viewer="document" href="{{ asset('storage/'.$settings->qr_path) }}">View platform receiving QR</a>@endif
        @else<p class="review-warning">Admin must configure the platform receiving account in GCash escrow before separate deposits can be submitted.</p>@endif
        @endif
        @if(($canPay && $agreement->funding_method === 'deposit' && $paymentDue > 0) || (($seller || $admin) && $funding['shortfall'] > 0))
        <form method="post" action="{{ route('returns.agreement.update', $returnRequest) }}" enctype="multipart/form-data">@include('shared.returns.partials.form-identity', ['action' => 'pay'])
            <label>Payment purpose<select name="purpose">
                @if($canPay && $agreement->funding_method === 'deposit' && $paymentDue > 0)<option value="return_budget">Pay return fee — {{ peso($paymentDue / 100) }}</option>@endif
                @if(($seller || $admin) && $funding['shortfall'] > 0)<option value="refund_recovery">Recover funds for buyer refund — {{ peso($funding['shortfall']) }}</option>@endif
            </select></label>
            <label>Exact amount transferred<input name="amount" type="number" min="0.01" step="0.01" required></label>
            <label>Incoming transfer reference<input name="reference" minlength="4" maxlength="150" required></label>
            <label>Payment receipt<input type="file" name="proof" accept="image/jpeg,image/png,image/webp" required></label>
            @include('shared.returns.partials.confirm', ['confirmation' => 'I transferred this money to platform escrow. A receipt still requires admin verification.'])<button type="submit">Submit escrow payment</button>
        </form>
        @endif
        @if($agreement->funding_method === 'deduct_refund' && !$agreement->reserved_cents && $agreement->budgetCents() > 0)<p class="review-warning">The deduction is waiting for sufficient verified refund funds. Reconcile the original payment or verify the refund-recovery shortfall. The system reserves the agreed deduction when funds become available.</p>@endif
        @if($admin)
        @php($canUnlock = $budgetOutstanding <= $fundingPlan['prospective_reservation_cents'] && $funding['shortfall'] <= 0 && $funding['funded'] > 0 && ($agreement->net_refund_cents == 0 || $agreement->destination_verified_at))
        @if(!$canUnlock)<p class="review-warning">Waiting for verified funds and the buyer's verified refund account. The resolution stays locked until all requirements are complete.</p>@endif
        <form method="post" action="{{ route('returns.agreement.update', $returnRequest) }}">@include('shared.returns.partials.form-identity', ['action' => 'ready'])
            @include('shared.returns.partials.confirm', ['confirmation' => 'The full transport budget and buyer refund are secured with verified funds.'])<button type="submit" @disabled(!$canUnlock)>Confirm funding &amp; unlock resolution</button>
        </form>
        @endif
    </div></details>
    @endif

    @if($agreement->funded_at && $agreement->physicalReturn() && ($seller || $admin) && $returnRequest->workflow?->stage === 'awaiting_collection')
    <details class="return-task" name="return-current-task" open><summary><h2>Schedule collection from buyer</h2></summary><div class="return-disclosure-body"><p>Agree on availability with the buyer first. Logistics assigns an approved rider; no cash is collected at the door.</p>
        <form method="post" action="{{ route('returns.agreement.update', $returnRequest) }}">@include('shared.returns.partials.form-identity', ['action' => 'schedule'])
            <label>Agreed pickup date and time (Philippine time)<input name="collection_at" type="datetime-local" value="{{ $agreement->collection_at?->copy()->timezone('Asia/Manila')->format('Y-m-d\TH:i') }}" required></label>
            @include('shared.returns.partials.confirm')<button type="submit">Schedule return pickup</button>
        </form>
    </div></details>
    @endif

    @if($agreement->resolution === 'exchange' && $returnRequest->status === 'item_received' && $user->isLogisticsStaff() && \App\Support\LogisticsAccess::isCenterStaff($user, (int) $agreement->seller_center_id) && !$agreement->replacement_reference)
    <details class="return-task" name="return-current-task" open><summary><h2>Dispatch the agreed replacement</h2></summary><div class="return-disclosure-body"><p>Record the replacement shipment reference and the rider responsible. The buyer must confirm actual receipt before its delivery fee becomes eligible.</p>
        <form method="post" action="{{ route('returns.agreement.update', $returnRequest) }}" enctype="multipart/form-data">@include('shared.returns.partials.form-identity', ['action' => 'replacement_dispatch'])
            <label>Replacement tracking / handover reference<input name="reference" maxlength="150" required></label>
            @include('shared.returns.partials.rider-select',['riderCenter'=>$agreement->seller_center_id,'riderLabel'=>'Replacement delivery rider','riderRequired'=>true])
            <label>Dispatch evidence<input name="proof" type="file" accept="image/jpeg,image/png,image/webp" required></label>
            @include('shared.returns.partials.confirm')<button type="submit">Record replacement dispatch</button>
        </form>
    </div></details>
    @endif
    @endif

    @if($agreement->replacement_reference)<details class="return-task" name="return-current-task" {{ $buyer && !$agreement->closed_at ? 'open' : '' }}><summary><h2>Replacement shipment</h2></summary><div class="return-disclosure-body"><p>{{ $agreement->replacement_reference }}</p><a class="review-action" data-image-viewer="document" href="{{ route('returns.file', ['kind'=>'replacement','id'=>$agreement->id]) }}">View replacement dispatch proof</a>
        @if($buyer && !$agreement->closed_at)<form method="post" action="{{ route('returns.agreement.update', $returnRequest) }}">@include('shared.returns.partials.form-identity', ['action' => 'replacement_received'])
            @include('shared.returns.partials.confirm', ['confirmation' => 'I received the agreed replacement in the expected condition.'])<button type="submit">Confirm replacement received</button>
        </form>@endif
    </div></details>@endif

    @if($agreement->payments->isNotEmpty())<details class="return-task" name="return-current-task" open><summary><h2>Escrow payment review</h2></summary><div class="return-disclosure-body"><p>Submitted is not funded. Admin must verify the actual incoming bank/e-wallet transaction.</p>
        @foreach($agreement->payments as $payment)
            @if($admin || (int)$payment->payer_user_id === (int)$user->id)
            <details name="return-receipt" {{ $admin && $payment->status==='submitted' ? 'open' : '' }}><summary>{{ ucwords(str_replace('_', ' ', $payment->purpose)) }} · {{ peso($payment->amount_cents / 100) }} · {{ ucfirst($payment->status) }}</summary>
                <p>Reference: {{ $payment->reference }}. {{ $payment->review_note }}</p><a class="review-action" data-image-viewer="document" href="{{ route('returns.file', ['kind'=>'deposit','id'=>$payment->id]) }}">View deposit receipt</a>
                @if($admin && $payment->status === 'submitted')<form method="post" action="{{ route('returns.deposit.review', $payment) }}">@csrf
                    <label>Decision<select name="decision"><option value="verify">Verify actual funds received</option><option value="reject">Reject receipt / incorrect transfer</option></select></label>
                    <label>Verification / rejection explanation<textarea name="note" minlength="10" maxlength="1500" required></textarea></label>
                    @include('shared.returns.partials.confirm', ['confirmation' => 'I checked the actual platform receiving account, not only the screenshot.'])<button type="submit">Record deposit review</button>
                </form>@endif
            </details>
            @endif
        @endforeach
    </div></details>@endif

    @if($admin && ($agreement->funded_at || $agreement->payments->contains('status','verified')))<details><summary>Reconcile unused return budget</summary><p>For a completed case, refund only unallocated money. Before collection, admin can cancel an undisputed verified budget, void unperformed services and return unused funds. This also handles a paid quote that could not activate. In-transit cancellation requires case review.</p>
        <form method="post" action="{{ route('returns.agreement.update', $returnRequest) }}">@include('shared.returns.partials.form-identity', ['action' => 'close_unused'])
            <label>Reconciliation / cancellation explanation<textarea name="note" minlength="10" maxlength="1500" required></textarea></label>
            @include('shared.returns.partials.confirm')<button type="submit">Reconcile unused budget</button>
        </form></details>@endif
    <details class="return-disclosure"><summary><h2>Agreement history</h2></summary><div class="return-disclosure-body"><ol>@foreach($agreement->events ?? [] as $event)<li>{{ ucwords(str_replace('_', ' ', $event['action'])) }} · Version {{ $event['version'] }} · {{ $event['at'] }}@if($event['note'])<p>{{ $event['note'] }}</p>@endif</li>@endforeach</ol></div></details>
</div>
<link rel="stylesheet" href="{{ asset('assets/shared/css/theme.css') }}">
<link rel="stylesheet" href="{{ asset('assets/shared/css/workflow-review.css') }}?v={{ filemtime(public_path('assets/shared/css/workflow-review.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/shared/css/return-settlements.css') }}?v={{ filemtime(public_path('assets/shared/css/return-settlements.css')) }}">
@endsection

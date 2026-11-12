    @if($seller && $editable)
    @php($proposalResolution=old('resolution',$agreement->resolution))
    <details class="return-task" name="return-current-task" {{ $agreement->status==='draft' ? 'open' : '' }}><summary><h2>{{ $agreement->status==='draft' ? 'Send a resolution proposal' : 'Edit and resend proposal' }}</h2></summary><div class="return-disclosure-body">
        <p>@if((int)$agreement->policy_version>=2)For refund-only or partial-refund remedies, the buyer keeps the item and you refund them directly. Physical buyer-fault returns deduct original seller-funded costs and new return delivery automatically. For physical seller-fault returns, you pay return delivery separately and refund the full eligible paid item amount plus its original paid shipping; shipping is calculated automatically.@else Buyer fault means the buyer pays the return fees. Seller fault means you pay them.@endif Sending this proposal records your agreement; the buyer must accept this exact version.</p>
        <form method="post" action="{{ route('returns.agreement.update',$returnRequest) }}">@include('shared.returns.partials.form-identity',['action'=>'propose'])
            <label>Who is responsible?<select name="fault" required><option value="seller" @selected($agreement->fault==='seller')>Seller — wrong, faulty or damaged item supplied</option><option value="buyer" @selected($agreement->fault==='buyer')>Buyer — selected the wrong item, size or color</option></select></label>
            <label>Resolution<select name="resolution" required>@foreach(['return_refund'=>'Return and refund','refund_only'=>'Refund only — buyer keeps item','partial_refund'=>'Partial refund — buyer keeps item'] as $value=>$label)<option value="{{ $value }}" @selected($proposalResolution===$value)>{{ $label }}</option>@endforeach</select></label>
            <label>Item refund amount<input name="merchandise_amount" type="number" min="{{ $proposalResolution==='partial_refund'?'0.01':'0' }}" max="{{ ($agreement->maximum_refund_cents-($proposalResolution==='partial_refund'?1:0))/100 }}" step="0.01" value="{{ old('merchandise_amount',$agreement->merchandise_cents/100) }}" required></label>
            <p>For Partial refund, propose a positive amount below the full eligible item value of {{ peso($agreement->maximum_refund_cents/100) }}. The buyer must agree to that exact amount.</p>
            <label>Original shipping reimbursement (seller-fault only)<input name="shipping_amount" type="number" min="0" step="0.01" value="{{ $agreement->shipping_cents/100 }}"></label>
            <details><summary>Return hubs</summary><p>The original delivery supplies the recommended reverse route. Choose an available receiving hub; service prices are calculated automatically when sending the proposal.</p>
            <label>Buyer-side collection hub<select name="collection_center_id"><option value="">Use original buyer-side hub</option>@foreach($centers as $hub)<option value="{{ $hub->id }}" @selected((int)$agreement->collection_center_id===(int)$hub->id)>{{ $hub->name }}</option>@endforeach</select></label>
            @include('shared.returns.partials.destination-select',['destinationField'=>'seller_center_id','destinationLabel'=>'Seller-side receiving hub','selectedCenter'=>$agreement->seller_center_id,'destinationRequired'=>false])</details>
            <label>Explanation for the buyer<textarea name="note" minlength="10" maxlength="1500" required>{{ $agreement->reason_note }}</textarea></label>
            @include('shared.returns.partials.confirm',['confirmation'=>'I agree to this proposal and to pay the return fees if I am responsible.'])<button type="submit">Send resolution proposal</button>
        </form>
    </div></details>
    @endif


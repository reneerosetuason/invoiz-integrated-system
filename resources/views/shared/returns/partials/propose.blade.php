    @if($seller && $editable)
    <article><h2>{{ $agreement->status==='draft' ? 'Send a resolution proposal' : 'Edit and resend proposal' }}</h2>
        <p>@if((int)$agreement->policy_version>=2)For a buyer-fault return and refund, original seller-funded costs and new return delivery are deducted automatically. You deposit only the new delivery fee and refund the net amount directly. Seller-fault remedies keep their existing funding process.@else Buyer fault means the buyer pays the return fees. Seller fault means you pay them.@endif Sending this proposal records your agreement; the buyer must accept this exact version.</p>
        <form method="post" action="{{ route('returns.agreement.update',$returnRequest) }}">@include('shared.returns.partials.form-identity',['action'=>'propose'])
            <label>Who is responsible?<select name="fault" required><option value="seller" @selected($agreement->fault==='seller')>Seller — wrong, faulty or damaged item supplied</option><option value="buyer" @selected($agreement->fault==='buyer')>Buyer — selected the wrong item, size or color</option></select></label>
            <label>Resolution<select name="resolution" required>@foreach(['return_refund'=>'Return and refund','refund_only'=>'Refund only — buyer keeps item','partial_refund'=>'Partial refund — buyer keeps item','exchange'=>'Exchange — return and replacement'] as $value=>$label)<option value="{{ $value }}" @selected($agreement->resolution===$value)>{{ $label }}</option>@endforeach</select></label>
            <label>Item refund amount<input name="merchandise_amount" type="number" min="0" max="{{ $agreement->maximum_refund_cents/100 }}" step="0.01" value="{{ $agreement->merchandise_cents/100 }}" required></label>
            <label>Original shipping reimbursement (seller-fault only)<input name="shipping_amount" type="number" min="0" step="0.01" value="{{ $agreement->shipping_cents/100 }}"></label>
            <details><summary>Return hubs</summary><p>The original delivery supplies the recommended reverse route. Choose an available receiving hub; service prices are calculated automatically when sending the proposal.</p>
            <label>Buyer-side collection hub<select name="collection_center_id"><option value="">Use original buyer-side hub</option>@foreach($centers as $hub)<option value="{{ $hub->id }}" @selected((int)$agreement->collection_center_id===(int)$hub->id)>{{ $hub->name }}</option>@endforeach</select></label>
            @include('shared.returns.partials.destination-select',['destinationField'=>'seller_center_id','destinationLabel'=>'Seller-side receiving hub','selectedCenter'=>$agreement->seller_center_id,'destinationRequired'=>false])</details>
            <details><summary>Replacement details — exchange only</summary>
                <label>Replacement variation<select name="replacement_variant_id"><option value="">No variation</option>@foreach($returnRequest->product?->variants??[] as $variant)<option value="{{ $variant->id }}" @selected((int)$agreement->replacement_variant_id===(int)$variant->id)>{{ $variant->variant_type }}: {{ $variant->variant_value }}</option>@endforeach</select></label>
                <label>Expected replacement size, color and condition<input name="replacement_description" maxlength="500" value="{{ $agreement->replacement_description }}"></label>
            </details>
            <label>Explanation for the buyer<textarea name="note" minlength="10" maxlength="1500" required>{{ $agreement->reason_note }}</textarea></label>
            @include('shared.returns.partials.confirm',['confirmation'=>'I agree to this proposal and to pay the return fees if I am responsible.'])<button type="submit">Send resolution proposal</button>
        </form>
    </article>
    @endif


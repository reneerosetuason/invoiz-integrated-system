@if(isset($returnFundingReceipts) && $returnFundingReceipts->isNotEmpty())
<section class="po-panel" id="escrow-funding">
    <div class="po-section-heading"><div><h2>Incoming escrow receipts</h2><p>Open each receipt and confirm the actual funds received. Completed service payouts then become available in the remittance list below.</p></div></div>
    @foreach($returnFundingReceipts as $receipt)
    <article class="my-4 rounded-xl border border-gray-200 p-4" data-escrow-receipt="{{ $receipt->id }}">
        <p><b>Return #{{ $receipt->settlement->return_request_id }} · {{ peso($receipt->amount_cents/100) }}</b> · {{ $receipt->purpose==='return_budget'?'Service escrow':'Refund funding' }}</p>
        <a class="po-button" data-image-viewer="document" href="{{ route('returns.file',['kind'=>'deposit','id'=>$receipt->id]) }}">View payment receipt</a>
        <form method="post" action="{{ route('returns.deposit.review',$receipt) }}" class="po-release-form">
            @csrf<input type="hidden" name="decision" value="verify"><input type="hidden" name="note" value="I checked the payment receipt and confirmed the actual incoming escrow funds.">
            <label><input type="checkbox" name="confirm" value="1" required> I confirm the actual escrow funds arrived.</label>
            <button class="po-button" type="submit">Confirm escrow received</button>
        </form>
        <details><summary>Reject an unmatched receipt</summary><form method="post" action="{{ route('returns.deposit.review',$receipt) }}" class="po-release-form">@csrf
            <input type="hidden" name="decision" value="reject"><label>Reason<textarea name="note" minlength="10" maxlength="1500" required></textarea></label>
            <label><input type="checkbox" name="confirm" value="1" required> I checked this receipt.</label><button class="po-button" type="submit">Reject receipt</button>
        </form></details>
    </article>
    @endforeach
    {{ $returnFundingReceipts->links() }}
</section>
@endif

@php($visibleReceipts = $agreement->payments->filter(fn($p)=>($admin || (int)$p->payer_user_id===(int)$user->id) && (!($deliveryReceiptsOnly??false) || $p->purpose==='return_budget')))
@if($visibleReceipts->isNotEmpty())
<article><h2>{{ $admin ? 'Review uploaded payment receipts' : 'Your submitted receipts' }}</h2>
    <p>Each payment stays awaiting verification until admin opens its receipt and checks the actual incoming transfer.</p>
    @foreach($visibleReceipts as $payment)
    <details @if($admin && $payment->status==='submitted') open @endif>
        <summary>{{ $payment->purpose==='return_budget'?'Return transport fee':'Seller funding for buyer refund' }} · {{ peso($payment->amount_cents/100) }} · {{ $payment->status==='submitted'?'Awaiting admin verification':ucfirst($payment->status) }}</summary>
        <p><b>Transfer reference:</b> {{ $payment->reference }}</p>
        @if($payment->review_note)<p><b>Admin response:</b> {{ $payment->review_note }}</p>@endif
        <a class="review-action" data-image-viewer="document" href="{{ route('returns.file',['kind'=>'deposit','id'=>$payment->id,'v'=>substr(hash('sha256',$payment->proof_path),0,16)]) }}">{{ $admin ? 'Open receipt before verifying' : 'View uploaded receipt' }}</a>
        @if($admin && $payment->status==='submitted')
        <p>First open the receipt above. Compare its amount and reference with the real transaction in the platform account.</p>
        <form method="post" action="{{ route('returns.deposit.review',$payment) }}">@csrf
            <label>Transfer result<select name="decision"><option value="verify">Confirm successful transfer — actual funds received</option><option value="reject">Reject receipt / transfer not confirmed</option></select></label>
            <label>Verification or rejection explanation<textarea name="note" minlength="10" maxlength="1500" required></textarea></label>
            @include('shared.returns.partials.confirm',['confirmation'=>'I opened this receipt and checked the actual incoming bank / GCash transaction.'])
            <button type="submit">Record transfer verification</button>
        </form>
        @endif
    </details>
    @endforeach
</article>
@endif

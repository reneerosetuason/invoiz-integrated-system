@extends($layout)
@section('title', 'Return delivery escrow')
@section('content')
<div class="return-center return-agreement">
    <header class="review-head"><div><p class="review-eyebrow">Admin · Return service distribution</p><h1>Return delivery escrow</h1><p>Verify incoming delivery deposits, then pay completed return services. Item refunds and retained seller compensation are excluded from this delivery fund.</p></div></header>
    <nav class="return-nav"><a class="review-action" href="{{ route('returns.center') }}">Return center</a><a class="review-action" href="{{ route('returns.remittances',['delivery_only'=>1]) }}">All return delivery remittances</a></nav>
    @if(session('success'))<p role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<p class="review-warning" role="alert">{{ $errors->first() }}</p>@endif
    @forelse($rows as $agreement)
    @php
        $returnRequest=$agreement->returnRequest;
        $lines=$agreement->allocations->whereIn('role',\App\Models\ReturnAllocation::DELIVERY_ROLES);
        $pool=\App\Services\ReturnSettlementService::poolCents($agreement);
        $otherPaid=$agreement->allocations->whereNull('fund_payment_id')->whereNotIn('role',\App\Models\ReturnAllocation::DELIVERY_ROLES)->whereIn('status',['remitted','accepted'])->sum('amount_cents');
        $paid=$lines->whereIn('status',['remitted','accepted'])->sum('amount_cents');
        $available=max(0,min($agreement->transport_cents,max(0,$pool-$otherPaid))-$paid);
    @endphp
    <article><div class="return-heading"><h2>Return #{{ $returnRequest->id }}<small>{{ $returnRequest->item?->product_name }}</small></h2><b>{{ ucwords(str_replace('_',' ',$agreement->status)) }}</b></div>
        <dl class="return-money"><div><dt>Agreed delivery budget</dt><dd>{{ peso($agreement->transport_cents/100) }}</dd></div><div><dt>Verified, unspent delivery funds</dt><dd>{{ peso($available/100) }}</dd></div><div><dt>Delivery payments sent</dt><dd>{{ peso($paid/100) }}</dd></div></dl>
        @if(!$agreement->physicalDirectRefund())<p>Earlier agreement: the original payer and refund terms remain in force. Any item-refund recovery deposit is excluded here.</p>@endif
        <table class="return-fees"><thead><tr><th scope="col">Service</th><th scope="col">Amount</th><th scope="col">Work / payment</th></tr></thead><tbody>
            @foreach($lines as $line)<tr><td>{{ ucwords(str_replace('_',' ',$line->role)) }}</td><td>{{ peso($line->amount_cents/100) }}</td><td>{{ ucwords(str_replace('_',' ',$line->status)) }}</td></tr>@endforeach
        </tbody></table>
        <p>Reserved means work is pending. Eligible means the receiving handoff confirmed the service. Remitted means sent; accepted means the recipient confirmed receipt and it counts as earnings.</p>
        <a class="review-action" href="{{ route('returns.remittances',['return_id'=>$returnRequest->id,'delivery_only'=>1]) }}">Distribute return delivery</a>
        <a class="review-action" href="{{ route('returns.agreement',$returnRequest) }}">Read agreement</a>
        @include('shared.returns.partials.payment-receipts',['deliveryReceiptsOnly'=>true])
    </article>
    @empty<p>No buyer-fault return delivery escrows yet.</p>@endforelse
    {{ $rows->links() }}
</div>
<link rel="stylesheet" href="{{ asset('assets/shared/css/workflow-review.css') }}">
<link rel="stylesheet" href="{{ asset('assets/shared/css/return-settlements.css') }}">
@endsection

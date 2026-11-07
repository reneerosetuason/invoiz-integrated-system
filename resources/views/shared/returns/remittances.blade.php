@extends($layout)
@section('title', 'Return payments & earnings')
@section('content')
<div class="return-center return-agreement">
    <header class="review-head"><div><p class="review-eyebrow">Separate from original delivery payouts</p><h1>Return payments &amp; earnings</h1><p>Completed return work is paid from its verified budget. A sent remittance becomes confirmed earnings only after its recipient accepts receipt.</p></div></header>
    <nav class="return-nav"><a class="review-action" href="{{ route('returns.center') }}">Return center</a><a class="review-action" href="{{ route('returns.guide') }}">Button-by-button guide</a></nav>
    @if(session('success'))<p role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<p class="review-warning" role="alert">{{ $errors->first() }}</p>@endif
    <article><h2>Your confirmed return service earnings</h2><strong class="return-total">{{ peso($ownTotal / 100) }}</strong><p>Only accepted service allocations count here. Pending transfers, seller cost recovery and refunds of unused budgets are excluded.</p></article>
    @forelse($rows as $line)
    @php($canAccept = $line->canAccept($user))
    <article>
        <div class="return-heading"><h2>{{ ucwords(str_replace('_', ' ', $line->role)) }}<small>Return #{{ $line->settlement->return_request_id }} · {{ $line->settlement->returnRequest->item?->product_name }}</small></h2><b>{{ peso($line->amount_cents / 100) }}</b></div>
        <p class="review-status">{{ ucwords(str_replace('_', ' ', $line->status)) }}@if($line->center_id) · Hub #{{ $line->center_id }}@endif</p>
        <details><summary>View recipient, payment and actions</summary>
            <p>Recipient account: {{ $line->recipient_user_id ? '#'.$line->recipient_user_id : ($line->driver_id ? 'Inter-hub driver #'.$line->driver_id : 'Handling hub / awaiting assignment') }}.</p>
            @if($line->reference)<p>Transfer reference: {{ $line->reference }} · Sent {{ $line->remitted_at?->format('M j, Y g:i a') }}</p>
                @if($admin || $canAccept)<a class="review-action" data-image-viewer="document" href="{{ route('returns.file', ['kind'=>'remittance', 'id'=>$line->id]) }}">View remittance proof</a>@endif
            @endif
            @if($line->accepted_at)<p>Accepted {{ $line->accepted_at->format('M j, Y g:i a') }} by account #{{ $line->accepted_by }}.</p>@endif
            @if($line->note)<p>{{ $line->note }}</p>@endif
            @if(($admin || $canAccept) && $line->receiving_destination)
                <p><b>Verified receiving details:</b> {{ $line->receiving_destination['provider'] }} · {{ $line->receiving_destination['holder'] }}<br>{{ $line->receiving_destination['account'] }}</p>
            @endif
            @if($canAccept && in_array($line->status, ['reserved','eligible']))
            <form method="post" action="{{ route('returns.payout', $line) }}">@csrf<input type="hidden" name="action" value="destination">
                <label>Bank / e-wallet<input name="provider" maxlength="100" value="{{ $line->receiving_destination['provider'] ?? '' }}" required autocomplete="off"></label>
                <label>Account holder<input name="holder" maxlength="150" value="{{ $line->receiving_destination['holder'] ?? '' }}" required autocomplete="off"></label>
                <label>Receiving account number<input name="account" minlength="4" maxlength="100" value="{{ $line->receiving_destination['account'] ?? '' }}" required autocomplete="off"></label>
                @include('shared.returns.partials.confirm', ['confirmation'=>'I checked that these are the correct receiving details.'])<button type="submit">Verify remittance account</button>
            </form>
            @endif
            @if($admin && $line->status === 'eligible')
            <form method="post" action="{{ route('returns.payout', $line) }}" enctype="multipart/form-data">@csrf<input type="hidden" name="action" value="remit">
                <p>Transfer exactly {{ peso($line->amount_cents / 100) }} to the verified recipient. Uploading proof records a sent payment, not recipient acceptance.</p>
                <label>Outgoing bank / e-wallet transfer reference<input name="reference" minlength="4" maxlength="150" required></label>
                <label>Actual transfer proof<input type="file" name="proof" accept="image/jpeg,image/png,image/webp" required></label>
                @include('shared.returns.partials.confirm', ['confirmation'=>'I verified that the exact transfer was completed to this recipient.'])<button type="submit">Record return remittance</button>
            </form>
            @endif
            @if($canAccept && $line->status === 'remitted')
            <form method="post" action="{{ route('returns.payout', $line) }}">@csrf<input type="hidden" name="action" value="accept">
                @include('shared.returns.partials.confirm', ['confirmation'=>'I checked my actual receiving account and received this payment.'])<button type="submit">Accept remittance received</button>
            </form>
            @endif
            <a class="review-action" href="{{ route('returns.agreement', $line->settlement->returnRequest) }}">View return agreement</a>
        </details>
    </article>
    @empty<p>No return allocations are assigned to you yet.</p>@endforelse
    {{ $rows->links() }}
</div>
<link rel="stylesheet" href="{{ asset('assets/shared/css/workflow-review.css') }}?v={{ filemtime(public_path('assets/shared/css/workflow-review.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/shared/css/return-settlements.css') }}?v={{ filemtime(public_path('assets/shared/css/return-settlements.css')) }}">
@endsection

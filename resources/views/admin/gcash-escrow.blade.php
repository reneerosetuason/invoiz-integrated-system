@extends('admin.layout')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/shared/css/workflow-review.css') }}?v={{ filemtime(public_path('assets/shared/css/workflow-review.css')) }}">
<div class="escrow-review">
    <header class="review-head"><div><p class="review-eyebrow">Payments &amp; reconciliation</p>
    <h1 class="page-title">GCash escrow deposits</h1>
    <p>Verify actual incoming funds before unlocking order acceptance. Uploads and references alone do not confirm a transfer.</p>
    </div><a class="review-action" href="{{ route('admin.payments') }}">View allocations &amp; payouts &rarr;</a></header>
    @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <details class="escrow-settings" @if(!$settings) open @endif>
        <summary>Platform escrow receiving account</summary>
        <form method="POST" action="{{ route('admin.gcash.settings') }}" enctype="multipart/form-data" >
            @csrf
            <label>Account name<input class="input" name="account_name" value="{{ old('account_name', $settings?->account_name) }}" maxlength="150" required></label>
            <label>Transfer instructions / account number<textarea class="input" name="account_details" maxlength="500" required>{{ old('account_details', $settings?->account_details) }}</textarea></label>
            <label>Receiving QR (optional)<input type="file" name="qr" accept="image/jpeg,image/png,image/webp"></label>
            @if($settings?->qr_path)<img src="{{ asset('storage/'.$settings->qr_path) }}" alt="Escrow receiving QR" data-image-viewer tabindex="0" role="button" aria-label="Preview escrow receiving QR" style="width:200px;max-width:100%">@endif
            <button class="btn btn-primary" type="submit">Save receiving account</button>
        </form>
    </details>
    @forelse($deposits as $deposit)
        <article>
            <div class="escrow-deposit-head"><div><p class="review-eyebrow">Seller escrow deposit</p><h2>Order {{ $deposit->order?->order_number ?? '#'.$deposit->order_id }}</h2></div><strong class="escrow-amount">&#8369;{{ number_format($deposit->amount, 2) }}</strong></div>
            <p>{{ $deposit->seller?->name }}</p><span class="review-status">{{ ucwords(str_replace('_', ' ', $deposit->status)) }}</span><p>Transfer reference <span class="escrow-reference">{{ $deposit->reference ?? 'Not submitted' }}</span></p>
            @if(in_array($deposit->order?->status, ['cancelled', 'refunded', 'returned'], true))
                <p style="color:#9a3412;font-weight:600">Order {{ $deposit->order->status }}. Hold all allocations and reconcile any buyer refund with the account that received the funds. Cancelling an order does not transfer funds back to the buyer.</p>
            @endif
            @if($deposit->proof_path)<a data-image-viewer="document" class="review-action" href="{{ route('admin.gcash.proof', $deposit) }}"  >View seller escrow transfer proof</a>@endif
            @if($deposit->status === 'submitted')
                <form method="POST" action="{{ route('admin.gcash.review', $deposit) }}" >
                    @csrf
                    <label>Decision<select class="input" name="decision"><option value="approve">Confirm full funds received</option><option value="reject">Reject deposit</option></select></label>
                    <label>Reconciliation / rejection note<textarea class="input" name="note" required maxlength="1000"></textarea></label>
                    <label class="review-check"><input type="checkbox" name="verified_in_account" value="1"> I verified the entire PHP {{ number_format($deposit->amount, 2) }} in the platform receiving account. Required for approval.</label>
                    <button class="btn btn-primary" type="submit">Record review</button>
                </form>
            @endif
            @if($deposit->review_note)<p>Review: {{ $deposit->review_note }}</p>@endif
        </article>
    @empty<div class="escrow-empty"><strong>No seller deposits yet</strong><p>Seller transfers will appear here when they are ready for reconciliation.</p></div>@endforelse
    {{ $deposits->links() }}
</div>
@endsection

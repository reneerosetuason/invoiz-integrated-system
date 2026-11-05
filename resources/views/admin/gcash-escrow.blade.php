@extends('admin.layout')
@section('content')
<div class="card" style="padding:20px">
    <h1 class="page-title">GCash escrow deposits</h1>
    <p>Verify actual incoming funds before unlocking order acceptance. Uploads and references alone do not confirm a transfer.</p>
    <p><a href="{{ route('admin.payments') }}">View allocations and release stakeholder cuts →</a></p>
    @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <details style="margin:20px 0" @if(!$settings) open @endif>
        <summary>Platform escrow receiving account</summary>
        <form method="POST" action="{{ route('admin.gcash.settings') }}" enctype="multipart/form-data" style="display:grid;gap:12px;max-width:560px;margin-top:16px">
            @csrf
            <label>Account name<input class="input" name="account_name" value="{{ old('account_name', $settings?->account_name) }}" maxlength="150" required></label>
            <label>Transfer instructions / account number<textarea class="input" name="account_details" maxlength="500" required>{{ old('account_details', $settings?->account_details) }}</textarea></label>
            <label>Receiving QR (optional)<input type="file" name="qr" accept="image/jpeg,image/png,image/webp"></label>
            @if($settings?->qr_path)<img src="{{ asset('storage/'.$settings->qr_path) }}" alt="Escrow receiving QR" style="width:200px;max-width:100%">@endif
            <button class="btn btn-primary" type="submit">Save receiving account</button>
        </form>
    </details>
    @forelse($deposits as $deposit)
        <article style="border-top:1px solid #ddd;padding:18px 0;overflow-wrap:anywhere">
            <h2 style="font-size:16px">Order {{ $deposit->order?->order_number ?? '#'.$deposit->order_id }} · PHP {{ number_format($deposit->amount, 2) }}</h2>
            <p>{{ $deposit->seller?->name }} · {{ ucwords(str_replace('_', ' ', $deposit->status)) }} · Ref: {{ $deposit->reference ?? 'Not submitted' }}</p>
            @if(in_array($deposit->order?->status, ['cancelled', 'refunded', 'returned'], true))
                <p style="color:#9a3412;font-weight:600">Order {{ $deposit->order->status }}. Hold all allocations and reconcile any buyer refund with the account that received the funds. Cancelling an order does not transfer funds back to the buyer.</p>
            @endif
            @if($deposit->proof_path)<a href="{{ route('admin.gcash.proof', $deposit) }}" target="_blank" rel="noopener">View seller escrow transfer proof</a>@endif
            @if($deposit->status === 'submitted')
                <form method="POST" action="{{ route('admin.gcash.review', $deposit) }}" style="display:grid;gap:10px;max-width:560px;margin-top:12px">
                    @csrf
                    <label>Decision<select class="input" name="decision"><option value="approve">Confirm full funds received</option><option value="reject">Reject deposit</option></select></label>
                    <label>Reconciliation / rejection note<textarea class="input" name="note" required maxlength="1000"></textarea></label>
                    <label><input type="checkbox" name="verified_in_account" value="1"> I verified the entire PHP {{ number_format($deposit->amount, 2) }} in the platform receiving account. Required for approval.</label>
                    <button class="btn btn-primary" type="submit">Record review</button>
                </form>
            @endif
            @if($deposit->review_note)<p>Review: {{ $deposit->review_note }}</p>@endif
        </article>
    @empty<p>No seller deposits yet.</p>@endforelse
    {{ $deposits->links() }}
</div>
@endsection

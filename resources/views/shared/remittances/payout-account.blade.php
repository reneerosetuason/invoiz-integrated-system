@php($savedPayoutAccounts = \App\Support\PayoutAccounts::forActor(auth()->user()))
@if($savedPayoutAccounts->isNotEmpty())
@once
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/shared/css/payout-accounts.css') }}?v={{ filemtime(public_path('assets/shared/css/payout-accounts.css')) }}">
@endpush
@endonce
<section class="payout-accounts" aria-label="Saved payout details">
    <h2>Bank &amp; e-wallet details</h2>
    <p>Save your receiving account once for all order and return payments. Admin sees your latest saved details when reviewing your pay.</p>
    @if(session('payout_account_saved'))<p role="status" class="payout-account-success">{{ session('payout_account_saved') }}</p>@endif
    @if($errors->hasAny(['target','provider','holder','account']))<p role="alert">{{ $errors->first() }}</p>@endif
    @foreach($savedPayoutAccounts as $savedAccount)
    @php($bank = $savedAccount['details'])
    <details @if(old('target') === $savedAccount['key'] && $errors->any()) open @endif>
        <summary><span>{{ $savedAccount['label'] }}<small>{{ $bank ? $bank['provider'].' · ending '.substr($bank['account'], -4) : 'No receiving account saved' }}</small></span><span>{{ $bank ? 'Edit details' : 'Add details' }}</span></summary>
        <form method="POST" action="{{ route('remittances.payout-account.update') }}">
            @csrf
            <input type="hidden" name="target" value="{{ $savedAccount['key'] }}">
            @php($oldBank = old('target') === $savedAccount['key'])
            <label>Bank / e-wallet name<input name="provider" maxlength="100" value="{{ $oldBank ? old('provider') : ($bank['provider'] ?? '') }}" placeholder="e.g. BDO, BPI or GCash" required autocomplete="off"></label>
            <label>Account holder<input name="holder" maxlength="150" value="{{ $oldBank ? old('holder') : ($bank['holder'] ?? '') }}" required autocomplete="off"></label>
            <label>Account number / mobile number<input name="account" minlength="4" maxlength="100" value="{{ $oldBank ? old('account') : ($bank['account'] ?? '') }}" required autocomplete="off" spellcheck="false"></label>
            <p>Use the exact receiving details, including any leading zeros. Never enter a PIN, password or OTP.</p>
            <button type="submit">Save payout details</button>
        </form>
    </details>
    @endforeach
</section>
@endif

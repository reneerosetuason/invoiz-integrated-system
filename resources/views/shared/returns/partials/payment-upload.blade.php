@if($settings)
    <p><b>Platform receiving account:</b> {{ $settings->account_name }}</p>
    <p><small>Account details / instructions supplied by the platform:</small>{{ $settings->account_details }}</p>
    @if($settings->qr_path)<a class="review-action" data-image-viewer="document" href="{{ asset('storage/'.$settings->qr_path) }}">View payment QR</a>@endif
@else<p class="review-warning">The platform receiving account must be configured before submitting a receipt.</p>@endif
<form method="post" action="{{ route('returns.agreement.update',$returnRequest) }}" enctype="multipart/form-data">
    @include('shared.returns.partials.form-identity',['action'=>'pay'])
    <input type="hidden" name="purpose" value="{{ $paymentPurpose }}">
    <label>Amount to transfer<input name="amount" type="number" min="0.01" step="0.01" value="{{ number_format($paymentCents/100,2,'.','') }}" readonly required></label>
    <label>Bank / GCash transfer reference<input name="reference" minlength="4" maxlength="150" required></label>
    <label>{{ $receiptLabel }}<input type="file" name="proof" accept="image/jpeg,image/png,image/webp" required></label>
    <p>Upload a JPG, PNG or WebP screenshot, up to 5 MB. Submitting a receipt does not mark this transfer successful.</p>
    @include('shared.returns.partials.confirm',['confirmation'=>'I transferred this amount to the platform account and attached its actual receipt.'])
    <button type="submit" @disabled(!$settings)>{{ $uploadButton }}</button>
</form>

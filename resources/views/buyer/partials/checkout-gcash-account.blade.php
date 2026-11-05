<section class="checkout-gcash-account" data-seller-id="{{ $account['seller_id'] }}">
    <div class="checkout-gcash-store"><h3 data-store-name>{{ $account['store_name'] }}</h3><strong data-store-amount>₱{{ number_format($account['amount'], 2) }}</strong></div>
    <p class="checkout-setup-notice" data-setup-notice @if($account['available']) hidden @endif>This store has not added a GCash receiving QR yet. Choose cash on delivery or contact the store.</p>
    <div class="checkout-gcash-layout">
        <div class="checkout-qr-column" data-qr-column @if(!$account['qr_url']) hidden @endif>
            <a class="checkout-qr-link" data-qr-link @if($account['qr_url']) href="{{ $account['qr_url'] }}" @endif target="_blank" rel="noopener" aria-label="Open GCash receiving QR">
                <img data-receiving-qr @if($account['qr_url']) src="{{ $account['qr_url'] }}" @endif alt="{{ $account['store_name'] }} GCash receiving QR" width="232" height="232">
            </a>
            <a class="checkout-qr-open" data-qr-open @if($account['qr_url']) href="{{ $account['qr_url'] }}" @endif target="_blank" rel="noopener">Open QR at full size ↗</a>
            <p class="checkout-image-error" data-qr-error role="alert" hidden>The QR image could not load. Open it at full size or reload payment details before paying.</p>
        </div>
        <div class="checkout-gcash-instructions">
            <p class="checkout-account-name"><span>GCash account name</span><strong data-account-name>{{ $account['account_name'] ?: 'Not configured' }}</strong></p>
            <p class="checkout-note">Check the account name and amount in GCash before you send. No cash is due on delivery.</p>
            <div data-upload-fields @if(!$account['available']) hidden @endif>
                <label class="checkout-upload"><strong>GCash payment receipt</strong><span>Choose a photo or screenshot</span><input type="file" name="gcash_proofs[{{ $account['seller_id'] }}]" accept="image/jpeg,image/png,image/webp" @disabled(!$account['available']) @required($account['available'])></label>
                <small class="checkout-upload-help">JPG, PNG or WebP · Up to 5 MB</small>
                <p data-receipt-status class="checkout-receipt-status" role="status"></p>
                <p data-receipt-error class="checkout-payment-error" role="alert"></p>
                <img data-receipt-preview class="checkout-receipt-preview" alt="Selected GCash receipt" hidden>
                <p class="checkout-note">Your seller reviews this receipt. Uploading it does not confirm payment.</p>
            </div>
        </div>
    </div>
</section>

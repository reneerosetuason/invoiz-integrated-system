@php
    $deposit = \App\Models\GcashEscrowDeposit::where('order_id', $order->id)->first();
    $escrowAccount = \Illuminate\Support\Facades\DB::table('gcash_escrow_settings')->first();
@endphp
<div class="mb-4 rounded-lg border border-gray-200 p-3 space-y-3">
    <h4 class="font-bold">GCash escrow</h4>
    <p class="text-sm">Whole buyer payment: <strong>PHP {{ number_format($order->total_amount, 2) }}</strong>, including shipping. Do not deduct any commission or delivery fee.</p>
    <p class="text-sm">Status: <strong>{{ ucwords(str_replace('_', ' ', $deposit?->status ?? 'receipt_pending')) }}</strong></p>
    @if($deposit?->review_note)<p class="text-sm">Admin review: {{ $deposit->review_note }}</p>@endif
    @if(!in_array($order->status, ['cancelled', 'refunded', 'returned'], true))
        @if(!$deposit && $order->payment?->has_payment_proof)
            <form method="POST" action="{{ route('seller.gcash.verify', $order) }}" class="space-y-2">
                @csrf
                <label class="block text-sm"><input type="checkbox" name="confirm_received" value="1" required> I checked my GCash transactions and received this buyer's full payment.</label>
                <button class="btn btn-primary" type="submit">Verify buyer payment</button>
            </form>
        @elseif(in_array($deposit?->status, ['receipt_verified', 'rejected'], true))
            @if($escrowAccount)
                <p class="text-sm">Send the whole payment to <strong>{{ $escrowAccount->account_name }}</strong>: {{ $escrowAccount->account_details }}</p>
                @if($escrowAccount->qr_path)<a data-image-viewer="document" href="{{ asset('storage/'.$escrowAccount->qr_path) }}"  ><img src="{{ asset('storage/'.$escrowAccount->qr_path) }}" alt="Platform escrow receiving QR" style="width:220px;max-width:100%;height:auto"></a>@endif
                <form method="POST" action="{{ route('seller.gcash.deposit', $order) }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <label class="block text-sm">Amount sent (PHP)<input class="input" type="number" name="amount" value="{{ $order->total_amount }}" min="0.01" step="0.01" required></label>
                    <label class="block text-sm">Escrow transfer reference<input class="input" name="reference" maxlength="150" pattern="[A-Za-z0-9-]+" required></label>
                    <label class="block text-sm">Escrow transfer receipt<input class="input" type="file" name="escrow_proof" accept="image/jpeg,image/png,image/webp" required></label>
                    <p class="text-xs">JPG, PNG or WebP, up to 5 MB. This must be your transfer to escrow, separate from the buyer receipt.</p>
                    <button class="btn btn-primary" type="submit">Submit escrow deposit</button>
                </form>
            @else
                <p class="text-sm">Contact admin to configure the platform escrow receiving account.</p>
            @endif
        @elseif($deposit?->status === 'funded')
            <p class="text-sm">Admin confirmed the whole payment in escrow. You can accept and fulfil this order.</p>
        @else
            <p class="text-sm">Awaiting admin confirmation of funds. Order acceptance is locked.</p>
        @endif
    @endif
</div>

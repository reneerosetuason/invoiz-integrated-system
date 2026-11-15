<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-5 px-4 py-8">
        <a href="{{ route('failed-deliveries.index') }}" class="underline">Failed delivery resolutions</a>
        <h1 class="text-2xl font-bold">{{ $delivery->tracking_number }}</h1>
        <p>Buyer delivery attempts: {{ $delivery->failedAttempts() }} / 3. This resolution is separate from buyer return/refund claims.</p>
        @if(session('success'))<p class="rounded-xl bg-green-50 p-4">{{ session('success') }}</p>@endif
        @if($errors->any())<div class="rounded-xl bg-red-50 p-4">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        @if(!$case)
            @include('shared.failed-deliveries.action', ['action' => 'open', 'label' => 'Open seller resolution', 'hidden' => []])
        @else
            <section class="rounded-xl border bg-white p-4">
                <h2 class="font-bold">{{ ucwords(str_replace('_', ' ', $case->stage)) }}</h2>
                <p>Current hub: {{ $case->center?->name }}. Seller-side hub: {{ $case->sellerCenter?->name }}.</p>
                <p>{{ $case->decision === 'unclaimed' ? 'Unclaimed: held at the hub; no seller return trip or saleable-stock restoration.' : 'Return: hub handover, assigned transport, receiving-hub confirmation, and seller receipt are recorded separately.' }}</p>
                <p>Funding: {{ $case->funded_at ? 'Verified' : 'Awaiting verified seller payment' }}. Outstanding: PHP {{ number_format($due / 100, 2) }}.</p>
                @if(app(\App\Services\FailedDeliveryService::class)->canViewHandover($case, $actor))
                    @if($case->driver_proof_path)<a class="mr-4 underline" target="_blank" rel="noopener" href="{{ route('failed-deliveries.proof', ['driver', $case->id]) }}">View driver-to-hub handover proof</a>@endif
                    @if($case->seller_proof_path)<a class="underline" target="_blank" rel="noopener" href="{{ route('failed-deliveries.proof', ['seller', $case->id]) }}">View seller handover proof</a>@endif
                @endif
            </section>
            <div class="grid gap-4 md:grid-cols-2">
                @if($seller && !$case->accepted_at && !$case->closed_at)
                    @include('shared.failed-deliveries.action', ['action' => 'decide', 'label' => 'Request return to seller', 'hidden' => ['decision' => 'return']])
                    @include('shared.failed-deliveries.action', ['action' => 'decide', 'label' => 'Leave parcel unclaimed', 'hidden' => ['decision' => 'unclaimed']])
                @endif
                @if($staff && in_array($case->stage, ['awaiting_quote', 'awaiting_acceptance'], true))
                    @include('shared.failed-deliveries.action', ['action' => 'quote', 'label' => 'Prepare return quote', 'hidden' => []])
                @endif
                @if($seller && $case->stage === 'awaiting_acceptance')
                    @include('shared.failed-deliveries.action', ['action' => 'accept_quote', 'label' => 'Accept quote', 'hidden' => ['version' => $case->quote['version']]])
                @endif
                @if($seller && $case->accepted_at && !$case->funded_at && $due > 0)
                    @if($case->payments->contains('status', 'submitted'))
                        <p class="rounded-xl border bg-white p-4">Your receipt is awaiting verification. Do not send another transfer while it is under review.</p>
                    @else
                    <div class="rounded-xl border bg-white p-4">
                        <h2 class="font-bold">Pay the platform's configured receiving account</h2>
                        @if($settings)<p>{{ $settings->account_name }}</p><p class="whitespace-pre-wrap">{{ $settings->account_details }}</p>@else<p>Ask the platform administrator to configure the receiving account before transferring money.</p>@endif
                        @if($case->payments->contains('status', 'rejected'))<p>Your receipt was rejected. If you already transferred the funds, upload corrected proof using the same transfer reference instead of paying again.</p>@endif
                    </div>
                    @include('shared.failed-deliveries.action', ['action' => 'deposit', 'label' => 'Submit seller payment receipt', 'hidden' => []])
                    @endif
                @endif
                @php
                    $driverId = $actor instanceof \App\Models\HubTransportDriver ? $actor->id : ($actor instanceof \App\Models\User && \Illuminate\Support\Facades\Schema::hasTable('hub_transport_drivers') ? $actor->hubTransportDriver?->id : null);
                    $ownsDriver = $driverId && (int)$case->return_driver_id === (int)$driverId;
                    $ownsRider = $actor instanceof \App\Models\User && (int)$case->rider?->user_id === (int)$actor->id;
                    $actions = [];
                    if ($case->funded_at && $case->decision === 'return' && !$case->closed_at) {
                        if ($ownsDriver && $case->stage === 'at_hub' && !$case->driver_accepted_at) $actions['driver_accept'] = 'Accept return trip';
                        if ($ownsDriver && $case->stage === 'at_hub' && $case->driver_accepted_at) $actions['depart'] = 'Depart with return parcel';
                        if ($ownsDriver && $case->stage === 'in_transfer') $actions['arrive'] = 'Record receiving-hub handover';
                        if ($staff && $case->stage === 'hub_arrival') $actions['receive'] = 'Confirm hub receipt';
                        if ($staff && in_array($case->stage, ['at_hub', 'at_seller_hub'], true) && (int)$case->center_id === (int)$case->seller_center_id) $actions['assign_rider'] = 'Assign seller-delivery rider';
                        if ($ownsRider && $case->stage === 'seller_assigned') $actions['rider_collect'] = 'Confirm collection for seller delivery';
                        if ($ownsRider && $case->stage === 'seller_delivery') $actions['seller_arrival'] = 'Record seller handover';
                        if ($seller && $case->stage === 'seller_arrival') $actions['seller_receive'] = 'Confirm physical receipt';
                    }
                @endphp
                @foreach($actions as $action => $label)
                    @include('shared.failed-deliveries.action', ['action' => $action, 'label' => $label, 'hidden' => []])
                @endforeach
            </div>
            @if($seller || $admin)
                <section class="space-y-3">
                    <h2 class="text-lg font-bold">Seller payment receipts</h2>
                    @foreach($case->payments as $payment)
                        <div class="rounded-xl border p-4">
                            <p>PHP {{ number_format($payment->amount_cents / 100, 2) }} — {{ $payment->status }}</p>
                            <a href="{{ route('failed-deliveries.proof', ['deposit', $payment->id]) }}" target="_blank" rel="noopener" class="underline">Open private receipt</a>
                            @if($admin && $payment->status === 'submitted')
                                @include('shared.failed-deliveries.action', ['action' => 'review_payment', 'label' => 'Verify actual incoming payment', 'hidden' => ['payment_id' => $payment->id, 'approved' => 1]])
                                @include('shared.failed-deliveries.action', ['action' => 'review_payment', 'label' => 'Reject receipt', 'hidden' => ['payment_id' => $payment->id, 'approved' => 0]])
                            @endif
                        </div>
                    @endforeach
                </section>
            @endif
            <section class="space-y-3">
                <h2 class="text-lg font-bold">Service allocations</h2>
                <p>One outbound bill covers all attempts. The last-mile portion is split across the riders who made the recorded attempts. Existing remitted/accepted allocations are credited; future return services earn payment only after their handovers are complete.</p>
                @foreach($case->fees as $fee)
                    <div class="rounded-xl border bg-white p-4">
                        <h3 class="font-semibold">{{ ucfirst($fee->leg) }} / {{ ucwords(str_replace('_', ' ', $fee->role)) }}</h3>
                        <p>{{ $fee->recipientUser?->name ?? $fee->driver?->name ?? $fee->center?->name ?? 'Awaiting assigned worker' }}</p>
                        <p>PHP {{ number_format($fee->amount_cents / 100, 2) }}; already paid credit PHP {{ number_format($fee->credited_cents / 100, 2) }}; {{ $fee->status }}.</p>
                        @if($admin && $fee->status === 'eligible' && $case->funded_at && $fee->payableCents() > 0)
                            @php($account = \App\Support\PayoutAccounts::forRemittance($fee))
                            @if($account)<p>Receiving account: {{ $account['details']['provider'] ?? '' }} / {{ $account['details']['holder'] ?? '' }} / {{ $account['details']['account'] ?? '' }}</p>@endif
                            <form method="POST" action="{{ route('failed-deliveries.payout', $fee) }}" enctype="multipart/form-data" class="mt-3 space-y-2">
                                @csrf
                                <input name="reference" placeholder="Actual transfer reference" required maxlength="150" class="block rounded-lg border">
                                <input type="file" name="proof" required accept="image/jpeg,image/png,image/webp,application/pdf">
                                <label class="block"><input type="checkbox" name="confirm_sent" value="1" required> I sent this exact service payment to the saved recipient account.</label>
                                <button class="rounded-lg bg-teal-700 px-4 py-2 text-white">Record service payment</button>
                            </form>
                        @endif
                        @if($fee->status === 'remitted' && $fee->canAccept($actor))
                            <a class="underline" href="{{ route('failed-deliveries.proof', ['payout', $fee->id]) }}" target="_blank" rel="noopener">View transfer proof</a>
                            <form method="POST" action="{{ route('remittances.incoming.accept', ['failed_delivery', $fee->id]) }}" class="mt-2">
                                @csrf
                                <label><input type="checkbox" name="confirm_received" value="1" required> I received the actual funds.</label>
                                <button class="rounded-lg border px-4 py-2">Confirm payment received</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </section>
            <section class="rounded-xl border p-4">
                <h2 class="font-bold">Recorded history</h2>
                @foreach(($case->events ?? []) as $event)<p>{{ $event['at'] }} — {{ str_replace('_', ' ', $event['action']) }} → {{ str_replace('_', ' ', $event['stage']) }}</p>@endforeach
            </section>
        @endif
    </div>
</x-app-layout>

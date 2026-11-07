<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-5" data-hub-remittances>
        <header>
            <h1 class="text-2xl font-bold text-gray-900">Hub remittances</h1>
            <p class="mt-2 max-w-3xl text-sm leading-relaxed text-gray-600">Review your origin and destination hub cuts. Admin releases each payment first; confirm receipt after the money reaches your hub's receiving account. Only accepted payments count toward hub earnings.</p>
            <p class="mt-2 text-sm text-gray-600">Held / awaiting release &rarr; Remitted by Admin &rarr; Received by your hub. Allocating an escrow share does not send a bank transfer.</p>
        </header>

        @if(session('info'))
            <p role="status" class="rounded-xl border border-gray-200 bg-white p-4 text-sm">{{ session('info') }}</p>
        @endif
        @if($errors->any())
            <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <div class="grid gap-3 sm:grid-cols-3">
            @foreach(['accepted' => 'Confirmed hub earnings', 'remitted' => 'Ready to confirm receipt', 'awaiting_release' => 'Awaiting admin release'] as $key => $label)
                <div class="min-w-0 rounded-2xl border border-gray-200 bg-white p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</p>
                    <p class="mt-2 break-words text-2xl font-bold text-gray-900">₱{{ number_format($totals[$key], 2) }}</p>
                </div>
            @endforeach
        </div>

        <form method="GET" action="{{ route('remittances.hub-payouts.index') }}" class="flex flex-wrap items-end gap-3 rounded-2xl border border-gray-200 bg-white p-4">
            <div class="min-w-0 flex-1">
                <label for="payout-status" class="mb-1 block text-sm font-semibold text-gray-700">Payment status</label>
                <select id="payout-status" name="status" class="w-full rounded-xl border-gray-300 text-sm">
                    <option value="">All remittances</option>
                    @foreach(['remitted' => 'Ready to confirm receipt', 'accepted' => 'Accepted earnings', 'pending_remittance' => 'Awaiting admin transfer', 'held_in_escrow' => 'Held in escrow', 'voided' => 'Voided'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="min-h-[44px] rounded-xl border border-gray-300 bg-white px-5 text-sm font-semibold text-gray-800 hover:bg-gray-50">Apply filter</button>
        </form>

        <div class="space-y-3">
            @forelse($payouts as $payout)
                <article class="min-w-0 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5" data-payout-id="{{ $payout->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <h2 class="break-all text-sm font-bold text-gray-900">{{ $payout->order?->order_number ?: 'Payout #'.$payout->id }}</h2>
                            <p class="mt-1 break-words text-sm text-gray-600">{{ $payout->logisticsCenter?->name ?? 'Logistics hub' }} · {{ $payout->roleLabel() }}</p>
                            <p class="mt-2 text-xs font-semibold {{ $payout->status === 'accepted' ? 'text-emerald-700' : 'text-gray-600' }}">
                                {{ match($payout->status) {
                                    'accepted' => '✓ Received · recorded in hub earnings',
                                    'remitted' => '✓ Remitted by Admin · awaiting your receipt confirmation',
                                    'pending_remittance' => 'Awaiting admin transfer',
                                    'voided' => 'Voided',
                                    default => 'Held in escrow',
                                } }}
                            </p>
                        </div>
                        <p class="text-xl font-bold text-gray-900">₱{{ number_format($payout->amount, 2) }}</p>
                    </div>

                    <details class="mt-3">
                        <summary class="inline-flex min-h-[40px] cursor-pointer items-center rounded-lg border border-gray-200 px-3 text-xs font-semibold text-gray-700">View payment details</summary>
                        <dl class="mt-3 grid gap-3 rounded-xl bg-gray-50 p-3 text-sm sm:grid-cols-2">
                            <div class="min-w-0"><dt class="text-xs text-gray-500">Transfer reference</dt><dd class="mt-1 break-all font-medium">{{ $payout->bank_reference ?: 'Not released yet' }}</dd></div>
                            <div class="min-w-0"><dt class="text-xs text-gray-500">Admin release date</dt><dd class="mt-1 font-medium">{{ $payout->remitted_at?->format('M d, Y · h:i A') ?? 'Not released yet' }}</dd></div>
                            <div class="min-w-0"><dt class="text-xs text-gray-500">Parcel</dt><dd class="mt-1 break-all font-medium">{{ $payout->delivery?->tracking_number ?? 'Not available' }}</dd></div>
                            <div class="min-w-0"><dt class="text-xs text-gray-500">Allocation</dt><dd class="mt-1 break-words">{{ $payout->rule_applied ?: $payout->roleLabel() }}</dd></div>
                            @if($payout->notes)<div class="min-w-0 sm:col-span-2"><dt class="text-xs text-gray-500">Payment notes</dt><dd class="mt-1 whitespace-pre-line break-words">{{ $payout->notes }}</dd></div>@endif
                        </dl>
                    </details>

                    @if($payout->status === 'remitted' && $payout->canAcceptHubPayout(auth()->user()))
                        <form method="POST" action="{{ route('remittances.hub-payouts.accept', $payout->id) }}" class="mt-4 flex flex-col gap-3 border-t border-gray-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                            @csrf
                            <label class="flex min-w-0 items-start gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="confirm_received" value="1" required class="mt-1 rounded border-gray-300">
                                <span>I confirm this payment has reached our hub's receiving account.</span>
                            </label>
                            <button type="submit" class="min-h-[44px] shrink-0 rounded-xl border border-teal bg-white px-4 text-sm font-bold text-teal hover:bg-teal-light">Confirm received</button>
                        </form>
                    @elseif($payout->status === 'remitted')
                        <p class="mt-3 text-xs text-gray-500">Only the receiving hub or its company manager can confirm receipt.</p>
                    @endif
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-5 py-12 text-center">
                    <h2 class="font-semibold text-gray-900">No hub remittances found</h2>
                    <p class="mt-2 text-sm text-gray-500">Hub cuts appear here when allocated to your logistics center.</p>
                </div>
            @endforelse
        </div>
        <div>{{ $payouts->links() }}</div>
    </div>
</x-app-layout>

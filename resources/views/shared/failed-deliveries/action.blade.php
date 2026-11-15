<form method="POST" action="{{ route('failed-deliveries.update', $delivery) }}" enctype="multipart/form-data" class="space-y-3 rounded-xl border bg-white p-4">
    @csrf
    <input type="hidden" name="action" value="{{ $action }}">
    @foreach(($hidden ?? []) as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endforeach
    <h3 class="font-semibold">{{ $label }}</h3>
    @if($action === 'quote')
        <label class="block">Seller-side receiving hub
            <select name="seller_center_id" class="mt-1 block w-full rounded-lg border" required>
                @foreach($centers as $hub)<option value="{{ $hub->id }}" @selected($hub->id == $case->seller_center_id)>{{ $hub->name }}</option>@endforeach
            </select>
        </label>
        <label class="block">Return truck (required when hubs differ)
            <select name="truck_id" class="mt-1 block w-full rounded-lg border">
                <option value="">Same-hub return</option>
                @foreach($trucks as $truck)<option value="{{ $truck->id }}">{{ $truck->plate_number ?? $truck->id }} / {{ $truck->driver?->name }}</option>@endforeach
            </select>
        </label>
        <p class="text-sm">Fees use the configured service rates and calculated road distance/weight. They cannot be entered manually.</p>
    @endif
    @if($action === 'assign_rider')
        <label class="block">Delivery rider
            <select name="rider_id" class="mt-1 block w-full rounded-lg border" required>
                @foreach($riders as $rider)<option value="{{ $rider->id }}">{{ $rider->name }}</option>@endforeach
            </select>
        </label>
    @endif
    @if(in_array($action, ['deposit', 'arrive', 'seller_arrival'], true))
        <label class="block">{{ $action === 'deposit' ? 'Actual transfer receipt' : 'Physical handover photo' }}
            <input type="file" name="proof" accept="{{ $action === 'deposit' ? 'image/jpeg,image/png,image/webp,application/pdf' : 'image/jpeg,image/png,image/webp' }}" required class="mt-1 block w-full">
        </label>
    @endif
    @if($action === 'deposit')
        <label class="block">Bank/e-wallet reference <input name="reference" maxlength="150" required class="mt-1 block w-full rounded-lg border"></label>
        <p>Transfer exactly PHP {{ number_format($due / 100, 2) }}. Uploading a receipt does not mark payment as verified.</p>
    @endif
    @if($action === 'review_payment')
        @if($hidden['approved'] ?? false)
            <label class="block"><input type="checkbox" name="confirm_verified" value="1" required> I opened this receipt and checked the actual incoming bank/e-wallet transfer.</label>
        @endif
        <label class="block">Review note <input name="note" maxlength="1000" class="mt-1 block w-full rounded-lg border"></label>
    @endif
    @if($action === 'decide' && ($hidden['decision'] ?? null) === 'unclaimed')
        <p>The parcel stays at the hub. It is not added back to saleable stock. You still owe completed outbound services; no new return trip is charged.</p>
        <label><input type="checkbox" name="confirm_unclaimed" value="1" required> I accept unclaimed storage and the displayed outbound service bill.</label>
    @endif
    @if($action === 'accept_quote')
        <label><input type="checkbox" name="confirm_quote" value="1" required> I accept this exact outbound and return-service quote.</label>
    @endif
    <button class="rounded-lg bg-teal-700 px-4 py-2 font-semibold text-white" type="submit">{{ $label }}</button>
</form>

@if(\Illuminate\Support\Facades\Schema::hasTable('return_allocations'))
@php($returnInbox = \App\Support\ReturnRemittanceInbox::page(auth()->user()))
<section class="my-5 min-w-0 rounded-2xl border border-gray-200 bg-white p-4 sm:p-5" data-return-remittance-inbox>
    <h2 class="text-lg font-bold text-gray-900">Return-delivery remittances</h2>
    <p class="mt-2 text-sm text-gray-600">Admin-sent return payments wait here until you confirm actual receipt. Only confirmed payments count as return earnings.</p>
    <div class="my-3 flex flex-wrap gap-x-6 gap-y-2 text-sm">
        <p>Awaiting your confirmation: <b>{{ peso($returnInbox['awaiting_cents']/100) }}</b></p>
        <p>Confirmed return earnings: <b>{{ peso($returnInbox['accepted_cents']/100) }}</b></p>
    </div>
    <a href="{{ route('returns.remittances') }}" class="inline-flex min-h-[40px] items-center rounded-lg border border-gray-300 px-3 text-sm font-semibold text-teal">Manage return receiving details</a>
    <div class="mt-4 space-y-3">
        @forelse($returnInbox['rows'] as $returnPayout)
        <article class="min-w-0 rounded-xl border border-gray-200 p-4" data-return-payout-id="{{ $returnPayout->id }}">
            <div class="flex flex-wrap justify-between gap-2">
                <h3 class="min-w-0 break-words font-semibold">Return #{{ $returnPayout->settlement->return_request_id }} · {{ ucwords(str_replace('_',' ',$returnPayout->role)) }}</h3>
                <b>{{ peso($returnPayout->amount_cents/100) }}</b>
            </div>
            <p class="mt-2 text-sm {{ $returnPayout->status==='accepted'?'text-emerald-700':'text-gray-700' }}">{{ $returnPayout->status==='accepted'?'✓ Receipt confirmed · recorded in return earnings':'✓ Sent by admin · awaiting your receipt confirmation' }}</p>
            <p class="mt-2 break-words text-xs text-gray-600">Transfer reference: <span class="break-all">{{ $returnPayout->reference }}</span></p>
            <p class="mt-1 text-xs text-gray-600">Sent <time class="whitespace-nowrap">{{ $returnPayout->remitted_at?->format('M j, Y g:i a') }}</time></p>
            <a href="{{ route('returns.file',['kind'=>'remittance','id'=>$returnPayout->id]) }}" data-image-viewer="document" class="mt-3 inline-flex min-h-[40px] items-center rounded-lg border border-gray-300 px-3 text-sm font-semibold text-teal">View remittance proof</a>
            @if($returnPayout->status==='remitted')
            <form method="post" action="{{ route('returns.payout',$returnPayout) }}" class="mt-3 space-y-3">
                @csrf<input type="hidden" name="action" value="accept">
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="confirm" value="1" required class="mt-1 rounded border-gray-300"><span>I checked my receiving account and received this return payment.</span></label>
                <button type="submit" class="min-h-[44px] rounded-lg border border-teal bg-white px-4 text-sm font-bold text-teal">Accept return remittance received</button>
            </form>
            @endif
        </article>
        @empty<p class="text-sm text-gray-500">No return-delivery payments have been sent to you yet.</p>@endforelse
    </div>
    {{ $returnInbox['rows']->links() }}
</section>
@endif

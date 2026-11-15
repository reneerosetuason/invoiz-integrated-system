<div class="card p-5 min-w-0" data-transfer-history>
    <h2 class="text-sm font-bold text-[#1B1B1E] uppercase tracking-wide mb-1">Transfer History</h2>
    <p class="text-xs text-[#6E6E73] mb-4">Manual staff/driver updates and automatic system events.</p>
    <ol class="space-y-3">
        @foreach($transfer->events->sortBy('created_at') as $event)
            <li class="border-l-2 border-[#16697A] pl-3 min-w-0">
                <div class="text-xs font-bold text-[#1B1B1E]">{{ str($event->event_type)->replace('_', ' ')->title() }}</div>
                <div class="text-[11px] text-[#6E6E73] mt-0.5">
                    {{ $event->created_at->format('M d, Y H:i') }}
                    @if($canViewOriginPrivate)
                        &middot; {{ $event->actor_name ?: 'System (automatic)' }}
                    @endif
                </div>
                @if($canViewOriginPrivate && filled($event->notes))
                    @php
                        $history = \App\Support\TransferHistory::describe($event);
                    @endphp
                    <p class="text-xs text-[#4B5563] mt-1 break-words" data-history-summary>{{ $history['summary'] }}</p>
                    @if($history['details'])
                        <details class="mt-1 min-w-0 text-[11px] text-[#6E6E73]">
                            <summary class="cursor-pointer text-[#16697A]">View full record</summary>
                            <pre class="mt-2 max-h-64 overflow-y-auto whitespace-pre-wrap break-all rounded-lg bg-[#F5F5F2] p-3" style="max-width:100%;overflow-wrap:anywhere">{{ $history['details'] }}</pre>
                        </details>
                    @endif
                @endif
            </li>
        @endforeach
    </ol>
</div>

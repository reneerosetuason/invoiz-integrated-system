<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 leading-tight">Notifications</h2>
                <p class="mt-1 text-sm text-gray-500">
                    @if($unreadCount > 0)
                        {{ $unreadCount }} unread notification{{ $unreadCount === 1 ? '' : 's' }}
                    @else
                        You're all caught up
                    @endif
                </p>
            </div>
            @if($unreadCount > 0)
                <button type="button" data-mark-all
                        class="shrink-0 text-sm font-semibold text-teal hover:text-teal-dark bg-teal-light/60 hover:bg-teal-light rounded-xl px-4 py-2 transition-colors">
                    Mark all read
                </button>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6">
            {{-- Filters: All / Unread + existing type categories --}}
            <div class="flex flex-wrap items-center gap-2 mb-4">
                <a href="{{ route('notifications.index', array_filter(['type' => $activeType])) }}"
                   class="text-sm font-semibold rounded-xl px-4 py-2 transition-colors {{ $filter !== 'unread' ? 'bg-teal text-white' : 'bg-white text-gray-600 border border-gray-200 hover:border-teal' }}">
                    All
                </a>
                <a href="{{ route('notifications.index', array_filter(['filter' => 'unread', 'type' => $activeType])) }}"
                   class="text-sm font-semibold rounded-xl px-4 py-2 transition-colors {{ $filter === 'unread' ? 'bg-teal text-white' : 'bg-white text-gray-600 border border-gray-200 hover:border-teal' }}">
                    Unread
                </a>
                <form method="GET" action="{{ route('notifications.index') }}" class="ms-auto">
                    @if($filter === 'unread')
                        <input type="hidden" name="filter" value="unread">
                    @endif
                    <select name="type" onchange="this.form.submit()"
                            aria-label="Filter by notification type"
                            class="text-sm font-medium rounded-xl px-3 py-2 bg-white text-gray-700 border border-gray-200 focus:border-teal focus:outline-none">
                        <option value="">All types</option>
                        @foreach($types as $type)
                            <option value="{{ $type }}" @selected($activeType === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                @forelse($notifications as $notification)
                    <div class="flex items-start gap-3 px-4 sm:px-5 py-4 {{ $loop->last ? '' : 'border-b border-gray-50' }} {{ $notification->is_read ? '' : 'bg-teal-light/25' }}">
                        <span class="text-xl flex-shrink-0 mt-0.5" aria-hidden="true">{{ $notification->icon }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <p class="text-sm {{ $notification->is_read ? 'font-medium' : 'font-bold' }} text-gray-900">
                                    {{ $notification->title }}
                                </p>
                                @if(!$notification->is_read)
                                    <span class="h-2 w-2 rounded-full bg-teal flex-shrink-0 mt-1.5" aria-label="Unread"></span>
                                @endif
                            </div>
                            @if($notification->message)
                                <p class="text-[13px] text-gray-500 mt-0.5 leading-relaxed">{{ $notification->message }}</p>
                            @endif
                            <div class="flex items-center gap-3 mt-1.5">
                                <span class="text-[11px] text-gray-400">{{ $notification->created_at?->format('M j, Y g:i A') }}</span>
                                <span class="text-[11px] font-semibold uppercase tracking-wide text-teal-dark/70">{{ str_replace('_', ' ', $notification->type) }}</span>
                                @if($notification->link)
                                    <a href="{{ $notification->link }}" class="text-[11px] font-semibold text-teal hover:text-teal-dark">Open</a>
                                @endif
                                @if(!$notification->is_read)
                                    <button type="button" data-mark-read="{{ $notification->id }}"
                                            class="text-[11px] font-semibold text-gray-500 hover:text-teal-dark">Mark read</button>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-12 text-center">
                        <p class="text-4xl mb-3" aria-hidden="true">🔕</p>
                        <p class="text-sm font-semibold text-gray-900">No notifications here</p>
                        <p class="text-[13px] text-gray-500 mt-1">
                            {{ $filter === 'unread' ? 'Everything is read. Switch to All to browse history.' : 'New operational updates will appear here.' }}
                        </p>
                    </div>
                @endforelse
            </div>

            @if($notifications->hasPages())
                <div class="mt-4">
                    {{ $notifications->links() }}
                </div>
            @endif
        </div>
    </div>

    <script>
        document.querySelectorAll('[data-mark-read]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                fetch('{{ route('notifications.index', [], false) }}/' + btn.dataset.markRead + '/read', {
                    method: 'PATCH',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                }).then(function () { window.location.reload(); });
            });
        });
        var markAll = document.querySelector('[data-mark-all]');
        if (markAll) {
            markAll.addEventListener('click', function () {
                fetch('{{ route('notifications.mark-all-read', [], false) }}', {
                    method: 'PATCH',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                }).then(function () { window.location.reload(); });
            });
        }
    </script>
</x-app-layout>

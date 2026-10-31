<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 leading-tight">Notifications</h1>
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

    <div>
        <div class="max-w-4xl">
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
                        <span class="w-8 h-8 rounded-full bg-[#E6F4F1] text-[#16697A] flex items-center justify-center shrink-0 mt-0.5" aria-hidden="true">
                            @if(str_contains($notification->type ?? '', 'message'))
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            @elseif(str_contains($notification->type ?? '', 'delivery') || str_contains($notification->type ?? '', 'order'))
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            @elseif(str_contains($notification->type ?? '', 'rider') || str_contains($notification->type ?? '', 'pickup'))
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1"/></svg>
                            @else
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            @endif
                        </span>
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
                        <div class="mx-auto w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 mb-3" aria-hidden="true">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                <line x1="3" y1="3" x2="21" y2="21" stroke-linecap="round" stroke-width="2" />
                            </svg>
                        </div>
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

    <script src="{{ asset('assets/logistics/js/notifications/index.js') }}" data-config="{{ json_encode([(route('notifications.index', [], false)), (csrf_token()), (route('notifications.mark-all-read', [], false)), (csrf_token())], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) }}"></script>
</x-app-layout>

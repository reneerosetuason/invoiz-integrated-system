<x-app-layout>
    @php
        $activeJson = $activeConversation ? [
            'id' => $activeConversation->id,
            'name' => $activeConversation->participant_name,
            'type' => $activeConversation->participant_type,
            'order_id' => $activeConversation->order_id,
            'tracking' => $activeConversation->delivery?->tracking_number,
        ] : null;
    @endphp
    <div x-data="messagesApp({
            conversations: @js($conversationsData),
            messages: @js($messagesData),
            active: @js($activeJson),
            totalUnread: {{ $totalUnread }},
            search: @js(request('search', '')),
            filter: @js(request('filter', 'all')),
            perPage: {{ (int) request('per_page', 10) }},
            csrf: @js(csrf_token())
        })"
         class="h-[calc(100vh-7rem)] flex bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">

        {{-- ================= Conversation List Panel ================= --}}
        <div class="w-full sm:w-80 lg:w-96 border-r border-gray-200 flex-col bg-gray-50/50 flex-shrink-0 {{ $activeConversation ? 'hidden sm:flex' : 'flex' }}">

            {{-- Header --}}
            <div class="px-4 py-4 border-b border-gray-200 bg-white">
                <div class="flex items-center justify-between mb-3">
                    <h1 class="text-lg font-bold text-gray-900">Messages</h1>
                    <span x-show="totalUnread > 0" x-text="totalUnread" x-cloak class="bg-secondary text-white text-xs font-bold px-2 py-0.5 rounded-full"></span>
                </div>

                {{-- Search (full page GET) --}}
                <form method="GET" action="{{ route('messages.index') }}" class="relative mb-3">
                    <input type="hidden" name="filter" value="{{ request('filter', 'all') }}">
                    @if(request('per_page'))
                        <input type="hidden" name="per_page" value="{{ request('per_page') }}">
                    @endif
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search messages or people..." autocomplete="off"
                           class="w-full pl-9 pr-9 py-2 bg-gray-100 border-0 rounded-xl text-sm text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-teal focus:bg-white transition">
                    @if(trim((string) request('search')) !== '')
                        <a href="{{ route('messages.index') }}" class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600" title="Clear search">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </a>
                    @endif
                </form>

                {{-- Filters (inbox states + participant types that actually exist) --}}
                <div class="flex gap-1.5 flex-wrap">
                    <a href="{{ request()->fullUrlWithQuery(['filter' => 'all', 'page' => null]) }}"
                       class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request('filter', 'all') === 'all' ? 'bg-teal text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                        All
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['filter' => 'unread', 'page' => null]) }}"
                       class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request('filter', 'all') === 'unread' ? 'bg-teal text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                        Unread
                    </a>
                    @foreach($roleFilters as $type)
                        <a href="{{ request()->fullUrlWithQuery(['filter' => $type, 'page' => null]) }}"
                           class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request('filter', 'all') === $type ? 'bg-teal text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                            {{ ucfirst(\Illuminate\Support\Str::plural($type)) }}
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Conversation List (Alpine-driven, auto-updates) --}}
            <div class="flex-1 overflow-y-auto">
                <template x-for="conv in sortedConversations" :key="conv.id">
                    <a href="#"
                       @click.prevent="selectConversation(conv.id)"
                       class="block px-4 py-3.5 border-b border-gray-100 transition-colors hover:bg-white"
                       :class="active && active.id === conv.id ? 'bg-white shadow-sm' : ''">
                        <div class="flex items-start gap-3">
                            <div class="relative flex-shrink-0">
                                <div class="h-11 w-11 rounded-full flex items-center justify-center text-sm font-bold"
                                     :class="conv.type === 'rider' ? 'bg-teal-light text-teal-dark' : 'bg-amber-100 text-amber-700'"
                                     x-text="(conv.name || '?').charAt(0).toUpperCase()"></div>
                                <span x-show="conv.type === 'rider'" class="absolute -bottom-0.5 -right-0.5 h-3.5 w-3.5 rounded-full border-2 border-white bg-green-400"></span>
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-2 mb-0.5">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <span class="text-sm font-semibold truncate" :class="conv.unread > 0 ? 'text-gray-900' : 'text-gray-700'" x-text="conv.name"></span>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide flex-shrink-0"
                                              :class="conv.type === 'rider' ? 'bg-teal-light text-teal-dark' : 'bg-amber-50 text-amber-700'"
                                              x-text="conv.type.charAt(0).toUpperCase() + conv.type.slice(1)"></span>
                                    </div>
                                    <span class="text-[11px] text-gray-400 whitespace-nowrap flex-shrink-0" x-text="agoLabel(conv.last_message_at)"></span>
                                </div>

                                <div x-show="conv.order_id" class="text-xs text-teal font-medium mb-0.5">
                                    Order #<span x-text="conv.order_id"></span><span x-show="conv.tracking"> · <span x-text="conv.tracking"></span></span>
                                </div>

                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-xs truncate" :class="conv.unread > 0 ? 'text-gray-800 font-medium' : 'text-gray-500'">
                                        <span x-show="conv.has_attachment" x-cloak class="inline-flex items-center mr-1 text-gray-400" title="Latest message has an attachment">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                        </span>
                                        <span x-text="conv.preview || 'No messages yet'"></span>
                                    </p>
                                    <span x-show="conv.unread > 0" x-text="conv.unread"
                                          class="inline-flex items-center justify-center h-5 min-w-[20px] px-1.5 rounded-full bg-secondary text-white text-[10px] font-bold flex-shrink-0"></span>
                                </div>
                            </div>
                        </div>
                    </a>
                </template>

                <div x-show="conversations.length === 0" class="flex flex-col items-center justify-center py-12 px-4 text-center">
                    <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mb-3">
                        <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                    </div>
                    <p class="text-sm font-semibold text-gray-700">
                        @if(request('filter', 'all') === 'unread')
                            No unread conversations.
                        @elseif(request('filter', 'all') !== 'all')
                            No conversations with {{ \Illuminate\Support\Str::plural(request('filter')) }} yet.
                        @elseif(trim((string) request('search')) !== '')
                            No messages found.
                        @else
                            No messages yet.
                        @endif
                    </p>
                    <p class="text-xs text-gray-500 mt-1">Delivery, pickup, order, and support conversations appear here automatically.</p>
                </div>
            </div>

            @if($conversations->count() > 0)
                <div class="px-4 py-2.5 border-t border-gray-200 bg-white flex items-center justify-between gap-2 flex-wrap">
                    <p class="text-[11px] text-gray-500">
                        Showing {{ $conversations->firstItem() }}–{{ $conversations->lastItem() }} of {{ $conversations->total() }} conversations
                    </p>
                    <x-per-page route="messages.index" align=""/>
                </div>
                <div class="border-t border-gray-100 bg-gray-50 px-2 py-1.5">
                    {{ $conversations->onEachSide(1)->links() }}
                </div>
            @endif
        </div>

        {{-- ================= Chat Panel ================= --}}
        <div class="flex-1 flex flex-col min-w-0" x-show="active">
            {{-- Chat Header --}}
            <div class="px-5 py-3.5 border-b border-gray-200 bg-white flex items-center gap-3 flex-shrink-0">
                <a href="{{ route('messages.index') }}" class="sm:hidden p-1.5 -ml-1.5 rounded-lg hover:bg-gray-100 text-gray-500" title="Back">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>

                <div class="relative flex-shrink-0">
                    <div class="h-10 w-10 rounded-full flex items-center justify-center text-sm font-bold bg-teal-light text-teal-dark"
                         x-text="active ? (active.name || '?').charAt(0).toUpperCase() : ''"></div>
                    <span x-show="active && active.type === 'rider'" class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-white bg-green-400"></span>
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-bold text-gray-900 truncate" x-text="active?.name"></h2>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide bg-teal-light text-teal-dark"
                              x-text="active ? active.type.charAt(0).toUpperCase() + active.type.slice(1) : ''"></span>
                    </div>
                    <p class="text-xs text-gray-500">
                        <span x-show="active && active.order_id">Order #<span x-text="active?.order_id"></span><span x-show="active?.tracking"> · <span x-text="active?.tracking"></span></span></span>
                        <span x-show="!active || !active.order_id">Conversation</span>
                    </p>
                </div>
            </div>

            {{-- Messages (Alpine-driven, auto-updates). Files can be dropped here to attach. --}}
            <div x-ref="thread" @dragover.prevent="dragging = true" @dragleave="dragging = false" @drop.prevent="dropAttachment($event)"
                 class="relative flex-1 overflow-y-auto px-5 py-4 space-y-1"
                 style="background: linear-gradient(180deg, #F7F6F2 0%, #FFFFFF 100%);">
                <div x-show="dragging" x-cloak class="pointer-events-none absolute inset-0 z-20 flex items-center justify-center rounded-xl border-2 border-dashed border-teal bg-teal-light/70 m-2">
                    <p class="text-sm font-semibold text-teal-dark">Drop file here to attach</p>
                </div>

                <template x-for="(msg, idx) in messages" :key="msg.id">
                    <div>
                        <div x-show="idx === 0 || messages[idx - 1].day !== msg.day" class="flex items-center justify-center py-3">
                            <span class="px-3 py-1 bg-gray-200/70 rounded-full text-[11px] font-medium text-gray-500" x-text="msg.dayLabel"></span>
                        </div>

                        <div class="flex mb-2" :class="msg.mine ? 'justify-end' : 'justify-start'">
                            <div class="max-w-[75%] group">
                                <div class="inline-block w-fit max-w-full px-3.5 py-2 rounded-2xl text-sm leading-snug text-left whitespace-pre-wrap break-words align-top" :class="msg.mine ? 'bg-teal text-white rounded-br-md' : 'bg-white border border-gray-200 text-gray-900 rounded-bl-md shadow-sm'"><span x-text="msg.deleted ? 'This message was deleted.' : msg.body" :class="msg.deleted ? 'italic opacity-60' : ''"></span><span x-show="msg.edited_at && !msg.deleted" class="text-[10px] italic opacity-70 ml-1.5">Edited</span></div>

                                {{-- Attachments --}}
                                <template x-for="att in (msg.deleted ? [] : msg.attachments)" :key="att.id">
                                    <div class="mt-1.5" :class="msg.mine ? 'text-right' : 'text-left'">
                                        <div class="inline-block bg-white border border-gray-200 rounded-xl p-2 max-w-[240px] text-left shadow-sm">
                                            <template x-if="att.is_image">
                                                <img :src="att.view_url" :alt="att.name"
                                                     class="max-h-40 w-auto max-w-full object-contain rounded-lg cursor-zoom-in mb-1.5"
                                                     @click="lightbox = { url: att.view_url, name: att.name }">
                                            </template>
                                            <p class="text-xs font-semibold text-gray-800 truncate" x-text="att.name"></p>
                                            <p class="text-[10px] text-gray-400 mb-1.5"><span x-text="att.size"></span><span x-show="att.is_pdf"> · PDF</span></p>
                                            <div class="flex gap-1.5">
                                                <a :href="att.view_url" target="_blank"
                                                   class="inline-flex items-center gap-1 px-2 py-1 bg-teal-light text-teal-dark hover:bg-teal hover:text-white rounded-lg text-[11px] font-semibold transition">
                                                    View
                                                </a>
                                                <a :href="att.download_url"
                                                   class="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 text-gray-600 hover:bg-gray-200 rounded-lg text-[11px] font-semibold transition">
                                                    Download
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <div class="flex items-center gap-1.5 mt-1 px-1 relative" :class="msg.mine ? 'justify-end' : 'justify-start'">
                                    <span class="text-[10px] text-gray-400" x-text="msg.time"></span>
                                    <template x-if="msg.mine && !msg.deleted">
                                        <div class="flex items-center gap-1">
                                            <div class="relative">
                                                <button type="button" @click="openMenu = (openMenu === msg.id ? null : msg.id)" class="p-1 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition" title="Message actions" :aria-expanded="openMenu === msg.id ? 'true' : 'false'">
                                                    <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><circle cx="10" cy="4" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="10" cy="16" r="1.6"/></svg>
                                                </button>
                                                <div x-show="openMenu === msg.id" @click.outside="openMenu = null" x-cloak
                                                     class="absolute bottom-full right-0 mb-1 w-32 rounded-xl border border-gray-200 bg-white shadow-lg py-1 z-30">
                                                    <button type="button" @click="openMenu = null; startEdit(msg)" class="block w-full text-left px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50">Edit</button>
                                                    <button type="button" @click="openMenu = null; deleteMessage(msg)" class="block w-full text-left px-3 py-1.5 text-xs font-medium text-red-500 hover:bg-red-50">Delete</button>
                                                </div>
                                            </div>
                                            <span x-show="msg.is_read" class="text-teal">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            </span>
                                            <span x-show="!msg.is_read" class="text-gray-300">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            </span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <div x-show="active && messages.length === 0" class="flex flex-col items-center justify-center h-full text-gray-400">
                    <p class="text-sm">No messages yet. Say hello!</p>
                </div>
            </div>

            {{-- Message Input --}}
            <div class="px-5 py-4 border-t border-gray-200 bg-white flex-shrink-0">
                {{-- Pre-send attachment preview --}}
                <div x-show="attachmentName" x-cloak class="mb-2.5 flex items-center gap-2.5 rounded-xl border border-gray-200 bg-gray-50 p-2 pr-2.5 max-w-sm">
                    <template x-if="attachmentIsImage && attachmentPreview">
                        <img :src="attachmentPreview" alt="Attachment preview" class="h-12 w-12 rounded-lg object-cover flex-shrink-0">
                    </template>
                    <template x-if="!attachmentIsImage || !attachmentPreview">
                        <span class="flex h-12 w-12 items-center justify-center rounded-lg bg-white border border-gray-200 text-gray-400 flex-shrink-0">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                        </span>
                    </template>
                    <span class="min-w-0 flex-1 truncate text-xs font-medium text-gray-700" x-text="attachmentName"></span>
                    <button type="button" @click="clearAttachment()" class="flex-shrink-0 p-1.5 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition" title="Remove attachment" aria-label="Remove attachment">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form @submit.prevent="sendMessage" class="flex items-end gap-3">
                    <div class="flex-1">
                        <div x-show="editing" x-cloak class="flex items-center justify-between bg-amber-50 border border-amber-200 rounded-t-xl px-3 py-1.5">
                            <span class="text-[11px] font-semibold text-amber-700">Editing message</span>
                            <button type="button" @click="cancelEdit" class="text-[11px] text-amber-700 hover:text-amber-900 font-semibold">Cancel</button>
                        </div>
                        <textarea x-ref="bodyInput" x-model="draft" @keydown.enter.exact.prevent="sendMessage" required maxlength="2000"
                                  :placeholder="editing ? 'Update your message...' : 'Type a message...'" rows="1"
                                  class="w-full px-4 py-2.5 bg-gray-100 border-0 rounded-xl text-sm text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-teal focus:bg-white resize-none transition"
                                  style="min-height: 42px;"
                                  :class="editing ? 'rounded-t-none' : ''"></textarea>
                    </div>

                    <label class="flex items-center justify-center h-[42px] w-[42px] rounded-xl bg-gray-100 text-gray-500 hover:bg-gray-200 cursor-pointer transition-all flex-shrink-0" title="Attach a file">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                        </svg>
                        <input type="file" class="hidden" x-ref="attachment" @change="previewAttachment()" accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv">
                    </label>

                    <button type="submit" :disabled="sending"
                            class="flex items-center justify-center h-[42px] w-[42px] rounded-xl bg-teal text-white hover:bg-teal-dark shadow-sm transition-all flex-shrink-0 disabled:opacity-60">
                        <svg x-show="!sending" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                        </svg>
                        <svg x-show="sending" x-cloak class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                    </button>
                </form>
                <p x-show="error" x-cloak x-text="error" class="mt-2 text-xs text-red-600"></p>
            </div>
        </div>

        {{-- No conversation selected --}}
        <div class="flex-1 hidden sm:flex flex-col items-center justify-center text-center px-6" x-show="!active">
            <div class="w-16 h-16 rounded-2xl bg-teal-light flex items-center justify-center mb-4">
                <svg class="h-8 w-8 text-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-1">No messages yet</h3>
            <p class="text-sm text-gray-500 max-w-sm">When a rider sends Logistics a message, the conversation will appear here automatically.</p>
        </div>

        {{-- Image lightbox --}}
        <div x-show="lightbox" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-6 bg-black/80" @click="lightbox = null" @keydown.escape.window="lightbox = null">
            <div class="relative max-w-5xl max-h-full">
                <img :src="lightbox?.url" :alt="lightbox?.name" class="max-w-full max-h-[85vh] object-contain rounded-xl shadow-2xl">
                <button type="button" @click="lightbox = null" class="absolute -top-3 -right-3 p-1.5 bg-white rounded-full shadow-lg text-gray-600 hover:text-gray-900" aria-label="Close preview">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function messagesApp(init) {
            return {
                conversations: init.conversations,
                messages: init.messages,
                active: init.active,
                totalUnread: init.totalUnread,
                search: init.search,
                filter: init.filter,
                perPage: init.perPage,
                csrf: init.csrf,
                draft: '',
                attachmentName: '',
                attachmentPreview: '',
                attachmentIsImage: false,
                dragging: false,
                openMenu: null,
                editing: null,
                sending: false,
                error: '',
                lightbox: null,
                pollTimer: null,

                init() {
                    this.scrollToBottom();
                    this.pollTimer = setInterval(() => this.poll(), 8000);
                    this.$watch('messages', () => this.$nextTick(() => this.scrollToBottom()));
                },

                get sortedConversations() {
                    return [...this.conversations].sort((a, b) => (b.last_message_at || '').localeCompare(a.last_message_at || ''));
                },

                scrollToBottom() {
                    this.$nextTick(() => {
                        const el = this.$refs.thread;
                        if (el) el.scrollTop = el.scrollHeight;
                    });
                },

                agoLabel(iso) {
                    if (!iso) return '';
                    const diff = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
                    if (diff < 60) return 'Just now';
                    if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
                    if (diff < 86400) return Math.floor(diff / 3600) + ' hr ago';
                    if (diff < 604800) return Math.floor(diff / 86400) + ' d ago';
                    return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                },

                async selectConversation(id) {
                    this.error = '';
                    try {
                        const r = await fetch('/messages/' + id, { headers: { 'Accept': 'application/json' } });
                        if (!r.ok) throw new Error('Could not open this conversation.');
                        const data = await r.json();
                        this.active = data.active;
                        this.messages = data.messages;
                        this.totalUnread = data.totalUnread;
                        this.conversations = this.conversations.map(c => c.id === id ? { ...c, unread: 0 } : c);
                        this.editing = null;
                        this.draft = '';
                        this.openMenu = null;

                        // Keep the selected conversation after a page refresh.
                        const params = new URLSearchParams(window.location.search);
                        params.set('conversation', id);
                        params.delete('page');
                        history.replaceState(null, '', window.location.pathname + '?' + params.toString());
                    } catch (e) {
                        this.error = e.message || 'Failed to open the conversation.';
                    }
                },

                async sendMessage() {
                    if (!this.active) return;
                    if (this.sending) return;
                    const body = this.draft.trim();
                    if (!body && !this.$refs.attachment.files.length) {
                        this.error = 'Please type a message before sending.';
                        return;
                    }
                    if (this.editing) {
                        await this.saveEdit();
                        return;
                    }

                    this.sending = true;
                    this.error = '';
                    try {
                        const fd = new FormData();
                        fd.append('conversation_id', this.active.id);
                        fd.append('body', body);
                        if (this.$refs.attachment.files[0]) {
                            fd.append('attachment', this.$refs.attachment.files[0]);
                        }
                        const r = await fetch('/messages', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                            body: fd,
                        });
                        if (!r.ok) {
                            const data = await r.json().catch(() => ({}));
                            throw new Error(data.message || 'The message could not be sent. Please try again.');
                        }
                        this.draft = '';
                        this.$refs.attachment.value = '';
                        this.clearAttachment();
                        await this.poll();
                    } catch (e) {
                        this.error = e.message || 'The message could not be sent.';
                    } finally {
                        this.sending = false;
                    }
                },

                startEdit(msg) {
                    this.editing = msg;
                    this.draft = msg.body;
                    this.$refs.bodyInput.focus();
                },

                formatSize(bytes) {
                    if (!bytes || bytes <= 0) return '';
                    const units = ['B', 'KB', 'MB'];
                    let size = bytes, unit = 0;
                    while (size >= 1024 && unit < units.length - 1) { size /= 1024; unit++; }
                    return (unit === 0 ? size : size.toFixed(1)) + ' ' + units[unit];
                },

                previewAttachment() {
                    const file = this.$refs.attachment.files[0];
                    this.clearAttachment(false);
                    if (!file) return;
                    if (file.size > 5 * 1024 * 1024) {
                        this.error = 'Attachment must not exceed 5 MB.';
                        this.$refs.attachment.value = '';
                        return;
                    }
                    this.attachmentName = file.name + (file.size ? ' · ' + this.formatSize(file.size) : '');
                    if ((file.type || '').startsWith('image/')) {
                        this.attachmentIsImage = true;
                        this.attachmentPreview = URL.createObjectURL(file);
                    }
                },

                clearAttachment(clearInput = true) {
                    if (this.attachmentPreview) {
                        URL.revokeObjectURL(this.attachmentPreview);
                        this.attachmentPreview = '';
                    }
                    this.attachmentIsImage = false;
                    this.attachmentName = '';
                    if (clearInput && this.$refs.attachment) this.$refs.attachment.value = '';
                },

                dropAttachment(event) {
                    this.dragging = false;
                    const file = event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0];
                    if (!file) return;
                    try {
                        this.$refs.attachment.files = event.dataTransfer.files;
                    } catch (e) {
                        this.error = 'Could not attach that file. Use the attach button instead.';
                        return;
                    }
                    this.previewAttachment();
                },

                cancelEdit() {
                    this.editing = null;
                    this.draft = '';
                },

                async saveEdit() {
                    if (!this.editing || !this.draft.trim()) return;
                    this.sending = true;
                    this.error = '';
                    try {
                        const r = await fetch('/messages/' + this.editing.id, {
                            method: 'PATCH',
                            headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify({ body: this.draft.trim() }),
                        });
                        if (r.status === 403) throw new Error('You can only edit your own messages.');
                        if (!r.ok) throw new Error('The message could not be updated.');
                        this.editing.body = this.draft.trim();
                        this.editing.edited_at = new Date().toISOString();
                        this.cancelEdit();
                        this.poll();
                    } catch (e) {
                        this.error = e.message || 'The message could not be updated.';
                    } finally {
                        this.sending = false;
                    }
                },

                async deleteMessage(msg) {
                    if (!confirm('Delete this message?')) return;
                    this.error = '';
                    try {
                        const r = await fetch('/messages/' + msg.id, {
                            method: 'DELETE',
                            headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                        });
                        if (r.status === 403) throw new Error('You can only delete your own messages.');
                        if (!r.ok) throw new Error('The message could not be deleted.');
                        msg.deleted = true;
                        msg.attachments = [];
                        this.poll();
                    } catch (e) {
                        this.error = e.message || 'The message could not be deleted.';
                    }
                },

                async poll() {
                    try {
                        const params = new URLSearchParams({
                            conversation: this.active ? this.active.id : '',
                            search: this.search || '',
                            filter: this.filter || 'all',
                            per_page: this.perPage || 10,
                        });
                        const r = await fetch('/messages/poll?' + params.toString(), { headers: { 'Accept': 'application/json' } });
                        if (!r.ok) return;
                        const data = await r.json();
                        this.conversations = data.conversations;
                        this.totalUnread = data.totalUnread;
                        if (data.active && this.active && data.active.id === this.active.id) {
                            this.messages = data.messages;
                        }
                    } catch (e) {
                        // Network hiccup: keep showing current data; next tick retries.
                    }
                },
            };
        }
    </script>
    @endpush
</x-app-layout>

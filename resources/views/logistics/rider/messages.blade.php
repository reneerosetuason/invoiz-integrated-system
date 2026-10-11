<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Messages — Rider</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        teal: { DEFAULT: '#16697A', dark: '#0E4A57', light: '#EAF4F3' },
                        secondary: '#F0A202',
                    },
                    fontFamily: { sans: ['Segoe UI', 'system-ui', '-apple-system', 'sans-serif'] },
                },
            },
        };
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="font-sans antialiased bg-gradient-to-br from-[#16697A] via-[#0E4A57] to-[#0B3B46] min-h-screen p-4 sm:p-8">
@php
    $initialMessages = $messages->map(fn ($m) => [
        'id' => $m->id,
        'mine' => $m->sender_type === 'rider',
        'deleted' => $m->isDeleted(),
        'body' => $m->isDeleted() ? '' : $m->body,
        'is_read' => $m->is_read,
        'edited_at' => $m->edited_at?->toISOString(),
        'created_at' => $m->created_at->toISOString(),
        'time' => $m->created_at->format('g:i A'),
        'day' => $m->created_at->format('Y-m-d'),
        'dayLabel' => $m->created_at->isToday() ? 'Today' : ($m->created_at->isYesterday() ? 'Yesterday' : $m->created_at->format('F j, Y')),
        'attachments' => $m->isDeleted() ? [] : $m->attachments->map(fn ($a) => [
            'id' => $a->id,
            'name' => $a->original_filename,
            'size' => $a->humanSize(),
            'is_image' => $a->isImage(),
            'is_pdf' => $a->isPdf(),
            'view_url' => route('rider.messages.attachments.view', $a),
            'download_url' => route('rider.messages.attachments.download', $a),
        ])->values(),
    ])->values();
@endphp
<div x-data="riderMessages({
        messages: {{ $initialMessages }},
        csrf: '{{ csrf_token() }}'
    })"
     class="max-w-3xl mx-auto bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col" style="height: calc(100vh - 4rem); min-height: 540px;">

    {{-- Header --}}
    <div class="px-5 py-4 border-b border-gray-200 bg-white flex items-center gap-3 flex-shrink-0">
        <img src="{{ asset('images/logo.png') }}" alt="Logo" class="h-10 w-10 rounded-xl object-cover">
        <div class="flex-1 min-w-0">
            <h1 class="text-sm font-bold text-gray-900">Logistics Support</h1>
            <p class="text-xs text-gray-500 truncate">Chat with the Logistics team · {{ $user->name }}</p>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-xs font-semibold text-gray-400 hover:text-red-500 transition">Log Out</button>
        </form>
    </div>

    {{-- Messages --}}
    <div x-ref="thread" class="flex-1 overflow-y-auto px-5 py-4 space-y-1" style="background: linear-gradient(180deg, #F7F6F2 0%, #FFFFFF 100%);">
        <template x-for="(msg, idx) in messages" :key="msg.id">
            <div>
                <div x-show="idx === 0 || messages[idx - 1].day !== msg.day" class="flex items-center justify-center py-3">
                    <span class="px-3 py-1 bg-gray-200/70 rounded-full text-[11px] font-medium text-gray-500" x-text="msg.dayLabel"></span>
                </div>

                <div class="flex mb-2" :class="msg.mine ? 'justify-end' : 'justify-start'">
                    <div class="max-w-[75%] group">
                        <div class="inline-block w-fit max-w-full px-3.5 py-2 rounded-2xl text-sm leading-snug text-left whitespace-pre-wrap break-words align-top" :class="msg.mine ? 'bg-teal text-white rounded-br-md' : 'bg-white border border-gray-200 text-gray-900 rounded-bl-md shadow-sm'"><span x-text="msg.deleted ? 'This message was deleted.' : msg.body" :class="msg.deleted ? 'italic opacity-60' : ''"></span><span x-show="msg.edited_at && !msg.deleted" class="text-[10px] italic opacity-70 ml-1.5">Edited</span></div>

                        <template x-for="att in (msg.deleted ? [] : msg.attachments)" :key="att.id">
                            <div class="mt-1.5" :class="msg.mine ? 'text-right' : 'text-left'">
                                <div class="inline-block bg-white border border-gray-200 rounded-xl p-2 max-w-[240px] text-left shadow-sm">
                                    <template x-if="att.is_image">
                                        <img :src="att.view_url" :alt="att.name" class="max-h-40 w-auto max-w-full object-contain rounded-lg mb-1.5">
                                    </template>
                                    <p class="text-xs font-semibold text-gray-800 truncate" x-text="att.name"></p>
                                    <p class="text-[10px] text-gray-400 mb-1.5"><span x-text="att.size"></span><span x-show="att.is_pdf"> · PDF</span></p>
                                    <div class="flex gap-1.5">
                                        <a :href="att.view_url" target="_blank" class="inline-flex items-center gap-1 px-2 py-1 bg-teal-light text-teal-dark hover:bg-teal hover:text-white rounded-lg text-[11px] font-semibold transition">View</a>
                                        <a :href="att.download_url" class="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 text-gray-600 hover:bg-gray-200 rounded-lg text-[11px] font-semibold transition">Download</a>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div class="flex items-center gap-1.5 mt-1 px-1" :class="msg.mine ? 'justify-end' : 'justify-start'">
                            <span class="text-[10px] text-gray-400" x-text="msg.time"></span>
                            <template x-if="msg.mine && !msg.deleted">
                                <div class="flex items-center gap-1">
                                    <button type="button" @click="startEdit(msg)" class="text-[10px] text-gray-400 hover:text-teal opacity-0 group-hover:opacity-100 transition">Edit</button>
                                    <button type="button" @click="deleteMessage(msg)" class="text-[10px] text-gray-400 hover:text-red-500 opacity-0 group-hover:opacity-100 transition">Delete</button>
                                    <span x-show="msg.is_read" class="text-teal">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    </span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div x-show="messages.length === 0" class="flex flex-col items-center justify-center h-full text-gray-400 text-center px-6">
            <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mb-3">
                <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
            </div>
            <p class="text-sm font-semibold text-gray-700">No messages yet</p>
            <p class="text-xs text-gray-500 mt-1">Send a message to the Logistics team below — they'll reply here.</p>
        </div>
    </div>

    {{-- Composer --}}
    <div class="px-5 py-4 border-t border-gray-200 bg-white flex-shrink-0">
        <form @submit.prevent="sendMessage" class="flex items-end gap-3">
            <div class="flex-1">
                <div x-show="editing" x-cloak class="flex items-center justify-between bg-amber-50 border border-amber-200 rounded-t-xl px-3 py-1.5">
                    <span class="text-[11px] font-semibold text-amber-700">Editing message</span>
                    <button type="button" @click="cancelEdit" class="text-[11px] text-amber-700 hover:text-amber-900 font-semibold">Cancel</button>
                </div>
                <textarea x-ref="bodyInput" x-model="draft" @keydown.enter.exact.prevent="sendMessage" required maxlength="2000"
                          placeholder="Type a message to Logistics..." rows="1"
                          class="w-full px-4 py-2.5 bg-gray-100 border-0 rounded-xl text-sm text-gray-900 placeholder-gray-400 focus:ring-2 focus:ring-teal focus:bg-white resize-none transition"
                          style="min-height: 42px;"
                          :class="editing ? 'rounded-t-none' : ''"></textarea>
            </div>
            <label class="flex items-center justify-center h-[42px] w-[42px] rounded-xl bg-gray-100 text-gray-500 hover:bg-gray-200 cursor-pointer transition-all flex-shrink-0" title="Attach a file">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                </svg>
                <input type="file" class="hidden" x-ref="attachment" accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv" @change="handleFile">
            </label>
            <button type="submit" :disabled="sending"
                    class="flex items-center justify-center h-[42px] w-[42px] rounded-xl bg-teal text-white hover:bg-teal-dark shadow-sm transition-all flex-shrink-0 disabled:opacity-60">
                <svg x-show="!sending" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                </svg>
                <svg x-show="sending" x-cloak class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
            </button>
        </form>
        <p x-show="attachmentName" x-cloak x-text="attachmentName" class="mt-1.5 text-[11px] text-gray-500 truncate"></p>
        <p x-show="error" x-cloak x-text="error" class="mt-1.5 text-xs text-red-600"></p>
    </div>
</div>

<script>
    function riderMessages(init) {
        return {
            messages: init.messages,
            csrf: init.csrf,
            draft: '',
            attachmentName: '',
            editing: null,
            sending: false,
            error: '',

            init() {
                this.scrollToBottom();
                setInterval(() => this.poll(), 8000);
                this.$watch('messages', () => this.$nextTick(() => this.scrollToBottom()));
            },

            scrollToBottom() {
                this.$nextTick(() => {
                    const el = this.$refs.thread;
                    if (el) el.scrollTop = el.scrollHeight;
                });
            },

            async sendMessage() {
                const body = this.draft.trim();
                if (!body && !this.$refs.attachment.files.length) {
                    this.error = 'Please type a message before sending.';
                    return;
                }
                if (this.editing) { await this.saveEdit(); return; }

                this.sending = true;
                this.error = '';
                try {
                    const fd = new FormData();
                    fd.append('body', body);
                    if (this.$refs.attachment.files[0]) fd.append('attachment', this.$refs.attachment.files[0]);
                    const r = await fetch('/rider/messages', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                        body: fd,
                    });
                    if (!r.ok) {
                        const data = await r.json().catch(() => ({}));
                        throw new Error(data.message || 'The message could not be sent.');
                    }
                    this.draft = '';
                    this.$refs.attachment.value = '';
                    this.attachmentName = '';
                    await this.poll();
                } catch (e) {
                    this.error = e.message || 'The message could not be sent.';
                } finally {
                    this.sending = false;
                }
            },

            startEdit(msg) { this.editing = msg; this.draft = msg.body; this.$refs.bodyInput.focus(); },
            cancelEdit() { this.editing = null; this.draft = ''; },

            async saveEdit() {
                if (!this.editing || !this.draft.trim()) return;
                this.sending = true;
                try {
                    const r = await fetch('/rider/messages/' + this.editing.id, {
                        method: 'PATCH',
                        headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ body: this.draft.trim() }),
                    });
                    if (!r.ok) throw new Error('The message could not be updated.');
                    this.editing.body = this.draft.trim();
                    this.editing.edited_at = new Date().toISOString();
                    this.cancelEdit();
                } catch (e) {
                    this.error = e.message;
                } finally {
                    this.sending = false;
                }
            },

            async deleteMessage(msg) {
                if (!confirm('Delete this message?')) return;
                try {
                    const r = await fetch('/rider/messages/' + msg.id, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                    });
                    if (!r.ok) throw new Error('The message could not be deleted.');
                    msg.deleted = true;
                    msg.attachments = [];
                } catch (e) {
                    this.error = e.message;
                }
            },

            async poll() {
                try {
                    const r = await fetch('/rider/messages/poll', { headers: { 'Accept': 'application/json' } });
                    if (r.ok) {
                        const data = await r.json();
                        this.messages = data.messages;
                    }
                } catch (e) { /* retry next tick */ }
            },

            // keep file label updated
            handleFile() {
                const f = this.$refs.attachment.files[0];
                this.attachmentName = f ? f.name : '';
            },
        };
    }
</script>
</body>
</html>

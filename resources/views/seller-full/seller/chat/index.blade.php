@extends('layouts.seller')

@section('title', 'Chat & Messaging')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4">
    <div>
        <h2 class="text-xl font-extrabold tracking-tight">Chat / Messaging</h2>
        <p class="mt-0.5 text-sm text-ink-light">Conversations with customers who bought from your store.</p>
    </div>
</div>

<div class="card mt-6 overflow-hidden">
    <div class="grid grid-cols-1 md:grid-cols-3">
        {{-- Conversation list --}}
        <div class="border-b border-borderline md:border-b-0 md:border-r">
            <div class="border-b border-borderline px-5 py-4">
                <h3 class="text-base font-bold">Conversations</h3>
                <p class="text-xs text-ink-light">{{ $conversations->count() }} thread(s)</p>
            </div>
            <div class="max-h-[calc(100vh-260px)] overflow-y-auto">
                @forelse($conversations as $conversation)
                    <a href="{{ route('seller.chat.show', $conversation->id) }}"
                       class="flex items-center gap-3 border-b border-borderline px-5 py-4 transition hover:bg-basebg {{ request()->route('conversation') == $conversation->id ? '!bg-[#EAF4F3]' : '' }}">
                        <div class="avatar h-10 w-10 shrink-0">{{ strtoupper(substr($conversation->buyer->first_name,0,1).substr($conversation->buyer->last_name,0,1)) }}</div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between">
                                <span class="truncate text-sm font-bold">{{ $conversation->buyer->full_name }}</span>
                                <span class="ml-2 shrink-0 text-[11px] text-ink-light">{{ $conversation->updated_at->diffForHumans() }}</span>
                            </div>
                            <div class="truncate text-xs text-ink-light">
                                @if($conversation->lastMessage)
                                    <span class="font-semibold text-ink">{{ $conversation->lastMessage->sender_id === auth()->id() ? 'You: ' : '' }}</span>{{ $conversation->lastMessage->body }}
                                @else
                                    {{ $conversation->subject }}
                                @endif
                            </div>
                        </div>
                        @if($conversation->unread_count > 0)
                            <span class="flex h-5 min-w-5 items-center justify-center rounded-full bg-[#F0A202] px-1.5 text-[11px] font-bold text-white">{{ $conversation->unread_count }}</span>
                        @endif
                    </a>
                @empty
                    <div class="px-5 py-16 text-center">
                        <x-icon name="chat" class="mx-auto h-10 w-10 text-ink-light" />
                        <p class="mt-3 font-semibold">No conversations yet</p>
                        <p class="text-sm text-ink-light">When buyers message you about an order, it appears here.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Placeholder detail --}}
        <div class="hidden flex-col items-center justify-center md:col-span-2 md:flex">
            <x-icon name="send" class="h-12 w-12 text-ink-light" />
            <p class="mt-4 text-lg font-bold">Select a conversation</p>
            <p class="text-sm text-ink-light">Choose a thread on the left to start replying.</p>
        </div>
    </div>
</div>
@endsection
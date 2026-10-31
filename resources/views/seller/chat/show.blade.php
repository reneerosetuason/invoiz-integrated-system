@extends('seller.layouts.app')

@section('title', 'Conversation')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4">
    <div>
        <a href="{{ route('seller.chat.index') }}" class="mb-2 inline-flex items-center gap-1 text-sm font-semibold text-ink-light hover:text-primary">
            <x-icon name="arrow-left" class="h-4 w-4" /> Back to conversations
        </a>
        <div class="flex items-center gap-3">
            <div class="avatar h-10 w-10">{{ strtoupper(substr($conversation->buyer->first_name,0,1).substr($conversation->buyer->last_name,0,1)) }}</div>
            <div>
                <h2 class="text-xl font-extrabold tracking-tight">{{ $conversation->buyer->full_name }}</h2>
                <p class="text-sm text-ink-light">{{ $conversation->buyer->email }}</p>
            </div>
        </div>
    </div>
    <span class="badge bg-soft !text-ink-light"><x-icon name="shopping-bag" class="h-3.5 w-3.5"/> {{ ($conversation->buyer->role ?? '') === 'admin' ? 'INVOIZ Support' : 'Buyer' }}</span>
</div>

<div class="card mt-6 flex h-[calc(100vh-300px)] flex-col overflow-hidden">
    {{-- Messages --}}
    <div class="flex-1 space-y-4 overflow-y-auto p-6" id="chat-scroll">
        @foreach($messages as $message)
            @php $mine = $message->sender_id === auth()->id(); @endphp
            <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-[75%]">
                    <div class="rounded-2xl px-4 py-3 text-sm leading-relaxed {{ $mine ? 'rounded-br-md text-white' : 'rounded-bl-md bg-basebg' }}"
                         style="{{ $mine ? 'background:#16697A;' : '' }}">
                        @include('partials.chat-product-card', ['card' => $message->product_card])
                        {{ $message->body }}
                    </div>
                    <div class="mt-1 flex items-center gap-2 px-1 text-[11px] text-ink-light">
                        <span>{{ $message->created_at->format('M j, g:i A') }}</span>
                        @if($mine)
                            <span class="inline-flex items-center gap-0.5 text-ink-light">
                                @if($message->is_read)
                                    <svg class="w-3 h-3 text-[#16697A] inline-block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13l4 4L17 7"/><path stroke-linecap="round" stroke-linejoin="round" d="M7 13l4 4L21 7"/></svg>
                                    <span>read</span>
                                @else
                                    <svg class="w-3 h-3 inline-block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    <span>sent</span>
                                @endif
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Reply form --}}
    <form method="POST" action="{{ route('seller.chat.reply', $conversation->id) }}"
          class="border-t border-borderline p-4 no-print">
        @csrf
        <div class="flex items-end gap-3">
            <textarea name="body" rows="1" required placeholder="Type your reply..."
                      class="textarea flex-1 resize-none" oninput="this.style.height='auto';this.style.height=Math.min(this.scrollHeight,160)+'px';"></textarea>
            <button type="submit" class="btn btn-primary h-[48px]">
                <x-icon name="send" class="h-4 w-4" /> Send
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/seller/js/chat/show.js') }}"></script>
@endpush

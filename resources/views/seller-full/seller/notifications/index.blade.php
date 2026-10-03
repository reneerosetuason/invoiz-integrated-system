@extends('layouts.seller')

@section('title', 'Notifications')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4">
    <div>
        <h2 class="text-xl font-extrabold tracking-tight">Notifications</h2>
        <p class="mt-0.5 text-sm text-ink-light">New orders, deliveries, and customer reviews.</p>
    </div>
    @if($notifications->where('is_read', false)->count() > 0)
        <form method="POST" action="{{ route('seller.notifications.read-all') }}">
            @csrf
            <button type="submit" class="btn btn-soft">
                <x-icon name="check" class="h-4 w-4" /> Mark all as read
            </button>
        </form>
    @endif
</div>

<div class="card mt-6 overflow-hidden">
    @forelse($notifications as $notification)
        <div class="flex items-start gap-4 border-b border-borderline px-6 py-4 transition hover:bg-basebg {{ $notification->is_read ? 'opacity-70' : 'bg-[#FCFAF5]' }}">
            <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $notification->is_read ? 'bg-borderline' : 'bg-secondary' }}"></span>
            <div class="min-w-0 flex-1">
                <div class="font-bold">{{ $notification->title }}</div>
                <div class="text-sm text-ink-light">{{ $notification->body }}</div>
                <div class="mt-0.5 text-[11px] text-ink-light">{{ $notification->created_at->format('M j, Y · g:i A') }}</div>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                @if($notification->order_id)
                    <a href="{{ route('seller.orders.show', $notification->order_id) }}" class="btn btn-outline btn-xs">
                        <x-icon name="eye" class="h-3.5 w-3.5" /> View
                    </a>
                @elseif($notification->product_id)
                    <a href="{{ route('seller.products.edit', $notification->product_id) }}" class="btn btn-outline btn-xs">
                        <x-icon name="eye" class="h-3.5 w-3.5" /> View
                    </a>
                @endif
                @if(! $notification->is_read)
                    <form method="POST" action="{{ route('seller.notifications.read', $notification->id) }}">
                        @csrf
                        <button class="btn btn-ghost btn-xs">Mark read</button>
                    </form>
                @endif
            </div>
        </div>
    @empty
        <div class="p-16 text-center">
            <x-icon name="bell" class="mx-auto h-10 w-10 text-ink-light" />
            <p class="mt-3 font-semibold">No notifications yet</p>
            <p class="text-sm text-ink-light">You'll be notified about new orders, deliveries, and reviews.</p>
        </div>
    @endforelse

    @if($notifications->hasPages())
        <div class="border-t border-borderline px-6 py-4">
            {{ $notifications->links() }}
        </div>
    @endif
</div>
@endsection
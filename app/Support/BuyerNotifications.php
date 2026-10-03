<?php

namespace App\Support;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\NotificationRead;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Buyer notification center, built from real shared-schema data:
 * order status timeline entries + unread seller chat messages.
 * Dismissed items are recorded in `notification_reads`.
 */
class BuyerNotifications
{
    public static function for(int $buyerId, int $limit = 20): Collection
    {
        $items = collect();

        // 1) Order updates — latest timeline entry of the buyer's recent orders.
        $orders = Order::where('buyer_id', $buyerId)->latest()->limit(10)->get();
        if ($orders->isNotEmpty()) {
            $histories = OrderStatusHistory::whereIn('order_id', $orders->pluck('id'))
                ->orderByDesc('id')->get()->groupBy('order_id');
            foreach ($orders as $o) {
                $h = $histories->get($o->id)?->first();
                if (!$h) {
                    continue;
                }
                $itemCount = \App\Models\OrderItem::where('order_id', $o->id)->count();
                $names = \App\Models\OrderItem::where('order_id', $o->id)
                    ->orderBy('id')->limit(2)->pluck('product_name')->all();
                $title = (implode(', ', $names) ?: "Order #{$o->id}")
                    . ($itemCount > 2 ? ' +' . ($itemCount - 2) . ' more' : '')
                    . ' → ' . ucfirst(str_replace('_', ' ', $h->to_status));
                $items->push([
                    'key' => "osh:{$h->id}",
                    'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16.5 9.4 7.55 4.24"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.29 7 12 12 20.71 7"/><line x1="12" y1="22" x2="12" y2="12"/></svg>',
                    'title' => $title ?: ("Order #{$o->id} → " . ucfirst(str_replace('_', ' ', $h->to_status))),
                    'body' => $h->note ?: ('Total ₱' . number_format((float) $o->total_amount, 2)),
                    'time' => $h->created_at,
                    'url' => url('/orders'),
                ]);
            }
        }

        // 2) Unread seller chat messages.
        $convs = Conversation::with('lastMessage')
            ->where('buyer_id', $buyerId)->latest()->limit(10)->get();
        foreach ($convs as $c) {
            $last = $c->lastMessage;
            if (!$last || (int) $last->sender_id === $buyerId) {
                continue;
            }
            $unread = Message::where('conversation_id', $c->id)
                ->where('sender_id', '!=', $buyerId)->where('is_read', 0)->count();
            if ($unread === 0) {
                continue;
            }
                $items->push([
                    'key' => "chat:{$c->id}:{$last->id}",
                    'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
                'title' => "New message from Seller #{$c->seller_id}",
                'body' => Str::limit($last->body, 80),
                'time' => $last->created_at,
                'url' => url('/chat/seller/' . $c->seller_id),
            ]);
        }

        $read = NotificationRead::where('user_id', $buyerId)->pluck('notification_key')->all();

        return $items->reject(fn($i) => in_array($i['key'], $read, true))
            ->sortByDesc('time')->take($limit)->values();
    }

    public static function count(int $buyerId): int
    {
        return static::for($buyerId)->count();
    }

    public static function dismiss(int $buyerId, string $key): void
    {
        NotificationRead::firstOrCreate(
            ['user_id' => $buyerId, 'notification_key' => $key]
        );
    }

    public static function dismissAll(int $buyerId): void
    {
        foreach (static::for($buyerId, 50) as $item) {
            static::dismiss($buyerId, $item['key']);
        }
    }
}

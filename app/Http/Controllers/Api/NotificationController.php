<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderStatusHistory;
use App\Models\Review;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\NotificationRead;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Lightweight, schema-free notification inbox derived from the buyer's
     * order status activity and review responses.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $items = [];
        $readKeys = NotificationRead::where('user_id', $user->id)->pluck('notification_key')->all();

        $histories = OrderStatusHistory::query()
            ->whereHas('order', fn ($q) => $q->where('buyer_id', $user->id))
            ->with('order:id,id')
            ->latest('created_at')
            ->limit(30)
            ->get();

        foreach ($histories as $h) {
            $items[] = [
                'id'         => 'order-'.$h->id,
                'type'       => 'order',
                'title'      => 'Order #'.$h->order_id.' updated',
                'body'       => $this->statusMessage($h->to_status, $h->note),
                'order_id'   => $h->order_id,
                'created_at' => $h->created_at->toIso8601String(),
                'read'       => in_array('order-'.$h->id, $readKeys, true),
            ];
        }

        $reviews = Review::query()
            ->where('buyer_id', $user->id)
            ->latest()
            ->limit(10)
            ->get();

        foreach ($reviews as $r) {
            $items[] = [
                'id'         => 'review-'.$r->id,
                'type'       => 'review',
                'title'      => 'Thanks for your feedback!',
                'body'       => 'You rated a product '.$r->rating.'/5.',
                'order_id'   => $r->order_id,
                'created_at' => $r->created_at?->toIso8601String() ?? now()->toIso8601String(),
                'read'       => in_array('review-'.$r->id, $readKeys, true),
            ];
        }

        $conversations = Conversation::with('lastMessage')
            ->where('buyer_id', $user->id)
            ->whereHas('messages', fn ($q) => $q->where('sender_id', '!=', $user->id)->where('is_read', false))
            ->latest('updated_at')->limit(20)->get();
        foreach ($conversations as $conversation) {
            $last = $conversation->lastMessage;
            if (! $last || (int) $last->sender_id === (int) $user->id) continue;
            $key = 'chat-'.$conversation->id.'-'.$last->id;
            $items[] = [
                'id' => $key,
                'type' => 'chat',
                'title' => 'New seller message',
                'body' => \Illuminate\Support\Str::limit($last->body, 100),
                'order_id' => null,
                'conversation_id' => $conversation->id,
                'created_at' => $last->created_at->toIso8601String(),
                'read' => in_array($key, $readKeys, true),
            ];
        }

        usort($items, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));

        return response()->json(['notifications' => array_slice($items, 0, 40)]);
    }

    public function read(Request $request)
    {
        $validated = $request->validate(['id' => ['required', 'string', 'max:191']]);
        NotificationRead::firstOrCreate(['user_id' => $request->user()->id, 'notification_key' => $validated['id']]);
        return response()->json(['message' => 'Notification marked as read.']);
    }

    public function readAll(Request $request)
    {
        $items = $this->index($request)->getData(true)['notifications'] ?? [];
        foreach ($items as $item) {
            NotificationRead::firstOrCreate(['user_id' => $request->user()->id, 'notification_key' => $item['id']]);
        }
        return response()->json(['message' => 'Notifications marked as read.']);
    }

    protected function statusMessage(string $status, ?string $note): string
    {
        $map = [
            'pending'         => 'Your order is confirmed and being prepared.',
            'confirmed'       => 'The seller has confirmed your order.',
            'packed'          => 'Your items are packed and ready for pickup.',
            'out_for_delivery'=> 'Your order is out for delivery!',
            'delivered'       => 'Your order has been delivered. Enjoy!',
            'cancelled'       => 'Your order was cancelled.',
        ];

        $msg = $map[$status] ?? 'Order status changed to '.str_replace('_', ' ', $status).'.';

        return $note ? $msg.' '.$note : $msg;
    }
}

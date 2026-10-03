<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderStatusHistory;
use App\Models\Review;
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
                'read'       => false,
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
                'read'       => true,
            ];
        }

        usort($items, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));

        return response()->json(['notifications' => array_slice($items, 0, 40)]);
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
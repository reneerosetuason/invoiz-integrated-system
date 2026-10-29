<?php

namespace App\Support;

use App\Models\DeliveryStatusLog;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderCancellation
{
    public static function byBuyer(Order $order, string $reason, int $buyerId): bool
    {
        abort_unless((int) $order->buyer_id === $buyerId, 403);
        return self::cancel($order, $reason, $buyerId, false);
    }

    public static function bySeller(Order $order, string $reason, int $sellerId): bool
    {
        abort_unless($order->items()->where('seller_id', $sellerId)->exists(), 403);
        abort_if($order->items()->where('seller_id', '!=', $sellerId)->exists(), 409, 'A shared legacy order needs logistics review before cancellation.');
        return self::cancel($order, $reason, $sellerId, true);
    }

    private static function cancel(Order $order, string $reason, int $buyerId, bool $seller): bool
    {
        return DB::transaction(function () use ($order, $reason, $buyerId, $seller): bool {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'cancelled') {
                return false;
            }
            if (! ($locked->isCancellableByBuyer() || ($seller && $locked->status === 'ready_for_delivery'))) {
                throw ValidationException::withMessages(['order' => 'This order can no longer be cancelled.']);
            }

            $deliveries = $locked->deliveries()->orderBy('id')->lockForUpdate()->get();
            foreach ($deliveries as $delivery) {
                if ($delivery->picked_up_at !== null || ! in_array($delivery->status, [
                    'waiting_for_rider', 'assigned', 'accepted', 'going_to_pickup', 'arrived_at_shop', 'cancelled',
                ], true)) {
                    throw ValidationException::withMessages(['order' => 'A parcel has already left the seller and cannot be cancelled.']);
                }
            }

            $from = $locked->status;
            $locked->update(['status' => 'cancelled', 'cancellation_reason' => $reason]);
            foreach ($deliveries as $delivery) {
                if ($delivery->status === 'cancelled') {
                    continue;
                }
                $delivery->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => $reason]);
                DeliveryStatusLog::create([
                    'delivery_id' => $delivery->id,
                    'status' => 'cancelled',
                    'notes' => ($seller ? 'Cancelled by seller: ' : 'Cancelled by buyer: ') . $reason,
                    'changed_by' => $buyerId,
                ]);
            }
            foreach ($deliveries->pluck('rider_id')->filter()->unique()->sort() as $riderId) {
                \App\Services\FulfillmentWorkflow::releaseRider((int) $riderId);
            }
            OrderStatusHistory::create([
                'order_id' => $locked->id,
                'from_status' => $from,
                'to_status' => 'cancelled',
                'note' => ($seller ? 'Cancelled by seller: ' : 'Cancelled by buyer: ') . $reason,
            ]);
            Stock::restoreOrder($locked->load('items'));
            Payments::voidForCancel($locked, strict: true);
            Vouchers::restoreForOrder($locked, strict: true);
            return true;
        }, 3);
    }
}

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
        return DB::transaction(function () use ($order, $reason, $buyerId): bool {
            $locked = Order::where('buyer_id', $buyerId)->lockForUpdate()->findOrFail($order->id);
            if ($locked->status === 'cancelled') {
                return false;
            }
            if (! $locked->isCancellableByBuyer()) {
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
                    'notes' => 'Cancelled by buyer: ' . $reason,
                    'changed_by' => $buyerId,
                ]);
            }
            OrderStatusHistory::create([
                'order_id' => $locked->id,
                'from_status' => $from,
                'to_status' => 'cancelled',
                'note' => 'Cancelled by buyer: ' . $reason,
            ]);
            Stock::restoreOrder($locked->load('items'));
            Payments::voidForCancel($locked, strict: true);
            Vouchers::restoreForOrder($locked, strict: true);
            return true;
        }, 3);
    }
}

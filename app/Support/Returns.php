<?php

namespace App\Support;

use App\Models\ReturnRequest;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Return/refund completion: when a seller confirms the returned item has
 * arrived, the buyer is refunded through the payment ledger and the stock
 * is returned to the same side it was taken from.
 */
class Returns
{
    public static function complete(ReturnRequest $return): void
    {
        DB::transaction(function () use ($return) {
            $order = Order::query()->lockForUpdate()->findOrFail($return->order_id);
            $locked = ReturnRequest::query()->lockForUpdate()->findOrFail($return->id);
            if ($locked->status === 'refunded') {
                return;
            }

            if (! in_array($locked->status, ['approved', 'item_received'], true)) {
                throw ValidationException::withMessages(['return' => 'Approve the return before receiving it.']);
            }

            $item = OrderItem::query()->lockForUpdate()->findOrFail($locked->order_item_id);
            $alreadyReturned = (int) ReturnRequest::where('order_item_id', $item->id)
                ->where('id', '!=', $locked->id)->where('status', 'refunded')->sum('quantity');
            if ($locked->quantity < 1 || $alreadyReturned + $locked->quantity > $item->quantity) {
                throw ValidationException::withMessages(['return' => 'The return exceeds the remaining purchased quantity.']);
            }

            $refunded = (float) $locked->refund_amount > 0
                ? Payments::refund($order, (float) $locked->refund_amount, 'Return #' . $locked->id)
                : 0.0;
            Stock::restore($item, $locked->quantity);

            $locked->update([
                'status'      => 'refunded',
                'refund_amount' => $refunded,
                'resolved_at' => now(),
            ]);
        });
    }
}

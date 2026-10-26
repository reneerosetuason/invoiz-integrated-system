<?php

namespace App\Support;

use App\Models\ReturnRequest;
use Illuminate\Support\Facades\DB;

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
            $return->loadMissing(['item', 'order']);

            if ($return->item) {
                Stock::restore($return->item);
            }

            Payments::refund(
                $return->order,
                (float) $return->refund_amount ?: null,
                'Return #' . $return->id
            );

            $return->update([
                'status'      => 'refunded',
                'resolved_at' => now(),
            ]);
        });
    }
}

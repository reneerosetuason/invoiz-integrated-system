<?php

namespace App\Support;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\Remittance;
use App\Models\SellerNotification;

/**
 * Off-system COD cash flow, rider -> seller (bank-to-bank arranged in
 * private chat). The system never moves this money — it only RECORDS it:
 *
 *   pending   delivery completed, cash with the rider, seller not yet paid
 *   sent      rider marked the transfer sent (optional, informational)
 *   confirmed seller verified the cash arrived -> order may settle
 *
 * A sale settles (payment paid, ledger booked) only after EVERY delivered
 * parcel of the order is confirmed by its seller.
 */
class Remittances
{
    /** Cash the rider owes this seller's group: group buyer total minus rider keep. */
    public static function amountForGroup(Order $order, int $sellerId): float
    {
        $order->loadMissing(['items', 'orderVouchers.voucher']);
        $groupTotal = $order->sellerSubtotal($sellerId)
            - $order->voucherDiscountForSeller($sellerId)
            + Order::SHIPPING_FEE_PER_SELLER
            - $order->shippingWaivedForSeller($sellerId);
        return round(max(0, $groupTotal - Order::SHIPPING_FEE_PER_SELLER), 2);
    }

    /** Seller identity flavours that may own a remittance row. */
    public static function sellerKeys(int $sellerId): array
    {
        $ids = [(int) $sellerId];
        try {
            $seller = \App\Models\Seller::where('user_id', $sellerId)->first()
                ?? \App\Models\Seller::find($sellerId);
            if ($seller) {
                $ids = array_merge($ids, $seller->catalogIds());
            }
        } catch (\Throwable $e) {
        }
        return array_values(array_unique(array_map('intval', $ids)));
    }

    /** Ensure exactly one handoff row per delivery (idempotent). */
    public static function ensureForDelivery(Delivery $delivery): ?Remittance
    {
        try {
            if ($delivery->id && ($found = Remittance::where('delivery_id', $delivery->id)->first())) {
                return $found;
            }
            $order = $delivery->order;
            if (! $order) {
                return null;
            }
            $order->loadMissing(['items', 'orderVouchers.voucher']);
            $sid = (int) ($delivery->seller_id
                ?? $order->items->pluck('seller_id')->filter()->first());
            if (! $sid) {
                return null;
            }
            if (! $delivery->seller_id) {
                $delivery->update(['seller_id' => $sid]);
            }
            return Remittance::firstOrCreate(
                ['delivery_id' => $delivery->id],
                [
                    'order_id'  => $order->id,
                    'rider_id'  => $delivery->rider_id,
                    'seller_id' => $sid,
                    'amount'    => self::amountForGroup($order, $sid),
                    'status'    => 'pending',
                ]
            );
        } catch (\Throwable $e) {
            \Log::warning('Remittance ensure failed: ' . $e->getMessage());
            return null;
        }
    }

    /** Ensure handoffs for every delivery row of an order. */
    public static function ensureForOrder(Order $order): void
    {
        try {
            $deliveries = $order->deliveries()->get();
            if ($deliveries->isEmpty()) {
                $deliveries = $order->delivery()->get();
            }
            foreach ($deliveries as $delivery) {
                self::ensureForDelivery($delivery);
            }
        } catch (\Throwable $e) {
            \Log::warning('Remittance ensure-order failed: ' . $e->getMessage());
        }
    }

    /** Rider signals the bank transfer was sent (informational only). */
    public static function markSent(Remittance $remittance, int $riderId, ?string $reference = null): Remittance
    {
        if ($remittance->rider_id && (int) $remittance->rider_id !== $riderId) {
            abort(403, 'This handoff belongs to another rider.');
        }
        if ($remittance->status !== 'pending') {
            return $remittance;
        }
        $remittance->update([
            'rider_id'  => $remittance->rider_id ?? $riderId,
            'status'    => 'sent',
            'reference' => $reference ?? $remittance->reference,
            'sent_at'   => now(),
        ]);

        // Nudge the seller to verify — this is the money they are owed.
        try {
            SellerNotification::create([
                'seller_id'  => $remittance->seller_id,
                'order_id'   => $remittance->order_id,
                'product_id' => null,
                'type'       => 'remittance',
                'title'      => "Rider sent ₱" . number_format((float) $remittance->amount, 2),
                'body'       => 'The rider marked your cash handoff sent' . ($reference ? " (ref: {$reference})" : '') . '. Please confirm receipt so the sale settles.',
            ]);
        } catch (\Throwable $e) {
        }
        return $remittance->fresh();
    }

    /**
     * Seller verifies the cash arrived. Settles the whole order once every
     * delivered parcel is confirmed. Returns true when this confirm
     * triggered settlement.
     */
    public static function confirm(Remittance $remittance, array $sellerKeys, ?string $reference = null): bool
    {
        if (! in_array((int) $remittance->seller_id, array_map('intval', $sellerKeys), true)) {
            abort(403, 'This handoff belongs to another store.');
        }
        if ($remittance->status === 'confirmed') {
            return false;
        }
        $remittance->update([
            'status'       => 'confirmed',
            'reference'    => $reference ?? $remittance->reference,
            'confirmed_at' => now(),
        ]);

        $order = $remittance->order;
        if ($order && self::allDeliveredConfirmed((int) $order->id)) {
            Payments::settleForDelivery($order->fresh(['items', 'orderVouchers.voucher', 'payment']));
            return true;
        }
        return false;
    }

    /** True when every DELIVERED parcel of the order is seller-confirmed. */
    public static function allDeliveredConfirmed(int $orderId): bool
    {
        try {
            $delivered = Delivery::where('order_id', $orderId)
                ->where('status', 'delivered')->get();
            if ($delivered->isEmpty()) {
                return false;
            }
            foreach ($delivered as $delivery) {
                $ok = Remittance::where('delivery_id', $delivery->id)
                    ->where('status', 'confirmed')->exists();
                if (! $ok) {
                    return false;
                }
            }
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Backfill helper: record a presumed-settled handoff for old data. */
    public static function confirmForBackfill(?Delivery $delivery = null, ?Order $order = null): ?Remittance
    {
        try {
            $order = $order ?? ($delivery ? $delivery->order : null);
            if (! $order) {
                return null;
            }
            $order->loadMissing(['items', 'orderVouchers.voucher']);
            if ($delivery && $delivery->id) {
                if (Remittance::where('delivery_id', $delivery->id)->exists()) {
                    return null;
                }
                $sid = (int) ($delivery->seller_id
                    ?? $order->items->pluck('seller_id')->filter()->first());
                if (! $sid) {
                    return null;
                }
                return Remittance::create([
                    'order_id'     => $order->id,
                    'delivery_id'  => $delivery->id,
                    'rider_id'     => $delivery->rider_id,
                    'seller_id'    => $sid,
                    'amount'       => self::amountForGroup($order, $sid),
                    'status'       => 'confirmed',
                    'note'         => 'Backfilled: presumed settled with delivery.',
                    'confirmed_at' => $order->updated_at ?? now(),
                ]);
            }
            $made = null;
            foreach ($order->shippingGroupIds() as $sid) {
                if (Remittance::where('order_id', $order->id)->where('seller_id', $sid)->exists()) {
                    continue;
                }
                $made = Remittance::create([
                    'order_id'     => $order->id,
                    'delivery_id'  => null,
                    'rider_id'     => null,
                    'seller_id'    => $sid,
                    'amount'       => self::amountForGroup($order, $sid),
                    'status'       => 'confirmed',
                    'note'         => 'Backfilled: presumed settled with delivery.',
                    'confirmed_at' => $order->updated_at ?? now(),
                ]);
            }
            return $made;
        } catch (\Throwable $e) {
            \Log::warning('Remittance backfill failed: ' . $e->getMessage());
            return null;
        }
    }
}

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
        $calc = \App\Support\EscrowEarnings::calculateForSeller($order, $sellerId);

        return $calc['cod_escrow_remittance'];
    }

    /** Seller identity flavours that may own a remittance row. */
    public static function sellerKeys(int $sellerId): array
    {
        return [(int) $sellerId];
    }

    /**
     * Ensure exactly one handoff row per delivery (idempotent).
     *
     * Best-effort callers keep the historical null-on-failure contract.
     * Completion flows that require a handoff must use
     * ensureForDeliveryOrFail() so the enclosing transaction can roll back.
     */
    public static function ensureForDelivery(Delivery $delivery): ?Remittance
    {
        try {
            return self::ensureForDeliveryOrFail($delivery);
        } catch (\Throwable $e) {
            \Log::warning('Remittance ensure failed: ' . $e->getMessage());
            return null;
        }
    }

    /** Ensure a handoff row, propagating failures to the caller. */
    public static function ensureForDeliveryOrFail(Delivery $delivery): Remittance
    {
        if ($delivery->id && ($found = Remittance::where('delivery_id', $delivery->id)->first())) {
            return $found;
        }
        $order = $delivery->order()->first();
        if (! $order) {
            throw new \RuntimeException("Cannot create remittance for delivery {$delivery->id}: order is missing.");
        }
        $order->loadMissing(['items', 'orderVouchers.voucher']);
        $sid = (int) ($delivery->seller_id
            ?? $order->items->pluck('seller_id')->filter()->first());
        if (! $sid) {
            throw new \RuntimeException("Cannot create remittance for delivery {$delivery->id}: seller is missing.");
        }
        if (! $delivery->seller_id) {
            $delivery->update(['seller_id' => $sid]);
        }
        $remittance = Remittance::firstOrCreate(
            ['delivery_id' => $delivery->id],
            [
                'order_id'  => $order->id,
                'rider_id'  => $delivery->rider_id,
                'seller_id' => $sid,
                'amount'    => self::amountForGroup($order, $sid),
                'status'    => 'pending',
            ]
        );

        \App\Support\EscrowEarnings::syncRemittancesForOrder($order);

        return $remittance;
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
        return \Illuminate\Support\Facades\DB::transaction(function () use ($remittance, $sellerKeys, $reference) {
            // Always lock the order first to serialize confirmations across parcels.
            $order = Order::whereKey($remittance->order_id)->lockForUpdate()->firstOrFail();
            $locked = Remittance::whereKey($remittance->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array((int) $locked->seller_id, array_map('intval', $sellerKeys), true), 403, 'This handoff belongs to another store.');
            if ($locked->status === 'confirmed') {
                return false;
            }
            $locked->update(['status' => 'confirmed', 'reference' => $reference ?? $locked->reference, 'confirmed_at' => now()]);
            if (self::allDeliveredConfirmed((int) $order->id)) {
                Payments::settleForDelivery($order);
                return true;
            }
            return false;
        });
    }

    /** True when every DELIVERED parcel of the order is seller-confirmed. */
    public static function allDeliveredConfirmed(int $orderId): bool
    {
        try {
            $delivered = Delivery::where('order_id', $orderId)
                ->where('status', 'delivered')->get();
            if ($delivered->isEmpty() || Delivery::where('order_id', $orderId)->where('status', '!=', 'delivered')->exists()) {
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

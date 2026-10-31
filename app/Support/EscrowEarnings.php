<?php

namespace App\Support;

use App\Models\CommissionRate;
use App\Models\Delivery;
use App\Models\EscrowRemittance;
use App\Models\LogisticsCenter;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Rider;
use App\Models\RiderEarning;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Canonical Platform Escrow & Configurable Stakeholder Earnings Engine.
 *
 * Implements the 7-recipient earnings distribution:
 *   1. Origin Logistics Hub       — ₱30 per seller shipment (configurable)
 *   2. Pickup Rider               — ₱30 per item (configurable)
 *   3. Inter-Hub Transport Driver — ₱20 per seller shipment for shared transport when Origin Hub != Destination Hub (configurable)
 *   4. Destination Logistics Hub  — ₱30 per seller shipment when Origin Hub != Destination Hub (configurable)
 *   5. Last-Mile Delivery Rider   — ₱50 per seller shipment (configurable; always deducted from COD even on free-shipping vouchers)
 *   6. Admin                      — Configured commission rate (default 10% on net merchandise)
 *   7. Seller                     — Remaining product earnings after all deductions
 *
 * Free-shipping vouchers and store/platform merchandise vouchers NEVER reduce
 * rider, driver, or hub earnings. The Last-Mile Rider always deducts their
 * delivery fee from COD cash, and the Seller absorbs all deductions.
 */
class EscrowEarnings
{
    public const SETTING_KEYS = [
        'origin_hub_fee'        => 'earnings_origin_hub_fee',
        'pickup_rider_per_item' => 'earnings_pickup_rider_per_item',
        'inter_hub_driver_fee'  => 'earnings_inter_hub_driver_fee',
        'destination_hub_fee'   => 'earnings_destination_hub_fee',
        'last_mile_rider_fee'   => 'earnings_last_mile_rider_fee',
    ];

    /**
     * Return all configurable flat rates (with DB overrides in `settings`).
     *
     * @return array{
     *     origin_hub_fee: float,
     *     pickup_rider_per_item: float,
     *     inter_hub_driver_fee: float,
     *     destination_hub_fee: float,
     *     last_mile_rider_fee: float,
     *     admin_commission_rate: float
     * }
     */
    public static function rates(): array
    {
        $defaults = [
            'origin_hub_fee'        => (float) config('earnings.origin_hub_fee', 30.00),
            'pickup_rider_per_item' => (float) config('earnings.pickup_rider_per_item', 30.00),
            'inter_hub_driver_fee'  => (float) config('earnings.inter_hub_driver_fee', 20.00),
            'destination_hub_fee'   => (float) config('earnings.destination_hub_fee', 30.00),
            'last_mile_rider_fee'   => (float) config('earnings.last_mile_rider_fee', 50.00),
        ];

        try {
            if (Schema::hasTable('settings')) {
                $stored = Setting::whereIn('key', array_values(self::SETTING_KEYS))
                    ->pluck('value', 'key');
                foreach (self::SETTING_KEYS as $prop => $settingKey) {
                    if ($stored->has($settingKey) && is_numeric($stored->get($settingKey))) {
                        $defaults[$prop] = round(max(0.0, (float) $stored->get($settingKey)), 2);
                    }
                }
            }
        } catch (\Throwable $e) {
            // Safe fallback for isolated in-memory test schemas.
        }

        $defaults['admin_commission_rate'] = Payments::defaultCommissionRate();

        return $defaults;
    }

    /** Save updated earnings distribution rules into `settings`. */
    public static function updateRates(array $values): void
    {
        foreach (self::SETTING_KEYS as $prop => $settingKey) {
            if (array_key_exists($prop, $values) && is_numeric($values[$prop])) {
                Setting::set($settingKey, number_format(max(0.0, (float) $values[$prop]), 2, '.', ''));
            }
        }
    }

    public static function lastMileRiderFee(): float
    {
        return self::rates()['last_mile_rider_fee'];
    }

    public static function originHubFee(): float
    {
        return self::rates()['origin_hub_fee'];
    }

    public static function pickupRiderPerItemFee(): float
    {
        return self::rates()['pickup_rider_per_item'];
    }

    public static function interHubDriverFee(): float
    {
        return self::rates()['inter_hub_driver_fee'];
    }

    public static function destinationHubFee(): float
    {
        return self::rates()['destination_hub_fee'];
    }

    /** Resolve the delivery belonging to a seller group on an order, if any. */
    public static function deliveryForSeller(Order $order, int $sellerId): ?Delivery
    {
        try {
            if ($order->relationLoaded('deliveries')) {
                $found = $order->deliveries->first(fn ($d) => (int) $d->seller_id === $sellerId)
                    ?? ($order->deliveries->count() === 1 ? $order->deliveries->first() : null);
                if ($found) {
                    return $found;
                }
            }
            if ($order->relationLoaded('delivery') && $order->delivery) {
                if ((int) ($order->delivery->seller_id ?? $sellerId) === $sellerId) {
                    return $order->delivery;
                }
            }
            if ($order->id && Schema::hasTable('deliveries')) {
                return Delivery::where('order_id', $order->id)
                    ->where(function ($q) use ($sellerId) {
                        $q->where('seller_id', $sellerId)->orWhereNull('seller_id');
                    })
                    ->orderByRaw('CASE WHEN seller_id = ? THEN 0 ELSE 1 END', [$sellerId])
                    ->first();
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    /**
     * Determine origin hub, destination hub, and whether an inter-hub transfer
     * applies (Origin Hub != Destination Hub).
     *
     * @return array{origin_center_id: ?int, destination_center_id: ?int, is_inter_hub: bool}
     */
    public static function resolveHubRoute(?Delivery $delivery): array
    {
        if (! $delivery) {
            return [
                'origin_center_id'      => null,
                'destination_center_id' => null,
                'is_inter_hub'          => false,
            ];
        }

        $originId = $delivery->getAttribute('origin_center_id')
            ?: ($delivery->center_id ?: null);

        if (! $originId) {
            try {
                if ($delivery->relationLoaded('pickupRequest') && $delivery->pickupRequest) {
                    $originId = $delivery->pickupRequest->center_id;
                }
            } catch (\Throwable $e) {
            }
        }

        $destId = $delivery->getAttribute('destination_center_id')
            ?: ($delivery->getAttribute('destination_sorting_center_id') ?: ($delivery->center_id ?: null));

        $originId = $originId !== null ? (int) $originId : null;
        $destId = $destId !== null ? (int) $destId : null;

        $isInterHub = $originId !== null && $destId !== null && $originId !== $destId;

        return [
            'origin_center_id'      => $originId,
            'destination_center_id' => $destId,
            'is_inter_hub'          => $isInterHub,
        ];
    }

    /** Total item quantity for a seller on an order (minimum 1 when seller has a shipment). */
    public static function itemQuantityForSeller(Order $order, int $sellerId, ?Delivery $delivery = null): int
    {
        $items = $order->relationLoaded('items')
            ? $order->items->where('seller_id', $sellerId)
            : $order->items()->where('seller_id', $sellerId)->get();

        $qty = (int) $items->sum(fn ($it) => max(0, (int) ($it->quantity ?? 0)));

        if ($qty <= 0 && $delivery) {
            try {
                $dItems = $delivery->relationLoaded('items') ? $delivery->items : $delivery->items()->get();
                $qty = (int) $dItems->sum(fn ($it) => max(0, (int) ($it->quantity ?? 0)));
            } catch (\Throwable $e) {
            }
        }

        return max(1, $qty);
    }

    /** Compute effective commission rate (supports per-category rates when configured). */
    public static function commissionForSellerItems(Order $order, int $sellerId, float $netMerch, ?float $overrideRate = null): array
    {
        $defaultRate = $overrideRate ?? Payments::defaultCommissionRate();
        if ($netMerch <= 0) {
            return ['rate' => $defaultRate, 'amount' => 0.0];
        }

        if ($overrideRate !== null) {
            return [
                'rate'   => $defaultRate,
                'amount' => round($netMerch * $defaultRate / 100, 2),
            ];
        }

        try {
            if (! Schema::hasTable('commission_rates') || ! Schema::hasTable('products')) {
                return [
                    'rate'   => $defaultRate,
                    'amount' => round($netMerch * $defaultRate / 100, 2),
                ];
            }

            $categoryRates = CommissionRate::whereNotNull('category_id')->pluck('rate', 'category_id');
            if ($categoryRates->isEmpty()) {
                return [
                    'rate'   => $defaultRate,
                    'amount' => round($netMerch * $defaultRate / 100, 2),
                ];
            }

            $items = $order->relationLoaded('items')
                ? $order->items->where('seller_id', $sellerId)
                : $order->items()->where('seller_id', $sellerId)->get();

            $grossTotal = (float) $items->sum(fn ($it) => $it->lineTotal ?? ((float) ($it->unit_price ?? $it->price ?? 0)) * ((int) ($it->quantity ?? 0)));
            if ($grossTotal <= 0) {
                return [
                    'rate'   => $defaultRate,
                    'amount' => round($netMerch * $defaultRate / 100, 2),
                ];
            }

            $productCategories = \App\Models\Product::withTrashed()
                ->whereIn('id', $items->pluck('product_id')->filter()->unique()->all())
                ->pluck('category_id', 'id');

            $weightedCommission = 0.0;
            foreach ($items as $item) {
                $line = $item->lineTotal ?? ((float) ($item->unit_price ?? $item->price ?? 0)) * ((int) ($item->quantity ?? 0));
                $lineNet = $netMerch * ($line / $grossTotal);
                $catId = $productCategories[$item->product_id] ?? null;
                $itemRate = ($catId !== null && $categoryRates->has($catId))
                    ? (float) $categoryRates->get($catId)
                    : $defaultRate;
                $weightedCommission += $lineNet * ($itemRate / 100);
            }

            $commissionAmount = round($weightedCommission, 2);
            $effectiveRate = round(($commissionAmount / $netMerch) * 100, 2);

            return [
                'rate'   => $effectiveRate,
                'amount' => $commissionAmount,
            ];
        } catch (\Throwable $e) {
            return [
                'rate'   => $defaultRate,
                'amount' => round($netMerch * $defaultRate / 100, 2),
            ];
        }
    }

    /**
     * Canonical per-seller shipment earnings distribution.
     *
     * Every surface (Checkout, Order model, Payments ledger, Remittances,
     * Logistics Transactions, Seller Order / Dashboard / Reports / Payouts,
     * Admin Payments & Commission) derives its numbers from this method so
     * buyer paid === sum of stakeholder shares (plus any explicit seller deficit).
     */
    public static function calculateForSeller(
        Order $order,
        int $sellerId,
        ?Delivery $delivery = null,
        ?float $commissionRate = null,
    ): array {
        $order->loadMissing(['items', 'orderVouchers.voucher']);

        $hasGroup = $order->items->where('seller_id', $sellerId)->isNotEmpty();
        if (! $hasGroup) {
            return [
                'has_group'                => false,
                'item_quantity'            => 0,
                'is_inter_hub'             => false,
                'origin_center_id'         => null,
                'destination_center_id'    => null,
                'gross_merch'              => 0.0,
                'merch_discount'           => 0.0,
                'net_merch'                => 0.0,
                'buyer_shipping_base'      => 0.0,
                'shipping_waived'          => 0.0,
                'buyer_shipping_paid'      => 0.0,
                'buyer_total_payable'      => 0.0,
                'origin_hub_fee'           => 0.0,
                'pickup_rider_fee'         => 0.0,
                'pickup_rider_rate'        => 0.0,
                'inter_hub_driver_fee'     => 0.0,
                'destination_hub_fee'      => 0.0,
                'last_mile_rider_fee'      => 0.0,
                'hub_fees_total'           => 0.0,
                'rider_fees_total'         => 0.0,
                'merch_logistics_deducted' => 0.0,
                'commission_rate'          => $commissionRate ?? Payments::defaultCommissionRate(),
                'admin_commission'         => 0.0,
                'seller_earning'           => 0.0,
                'seller_deficit'           => 0.0,
                'cod_rider_retains'        => 0.0,
                'cod_escrow_remittance'    => 0.0,
            ];
        }

        $rates = self::rates();
        $delivery = $delivery ?? self::deliveryForSeller($order, $sellerId);
        $route = self::resolveHubRoute($delivery);
        $itemQty = self::itemQuantityForSeller($order, $sellerId, $delivery);

        $grossMerch = $order->sellerSubtotal($sellerId);
        $merchDiscount = $order->voucherDiscountForSeller($sellerId);
        $netMerch = round(max(0.0, $grossMerch - $merchDiscount), 2);

        $buyerShippingBase = round($rates['last_mile_rider_fee'], 2);
        $shippingWaived = $order->shippingWaivedForSeller($sellerId);
        $buyerShippingPaid = round(max(0.0, $buyerShippingBase - $shippingWaived), 2);
        $buyerTotalPayable = round($netMerch + $buyerShippingPaid, 2);

        // Stakeholder worker & hub fees (never reduced by free-shipping or merch vouchers).
        $originHubFee = round($rates['origin_hub_fee'], 2);
        $pickupRiderRate = round($rates['pickup_rider_per_item'], 2);
        $pickupRiderFee = round($pickupRiderRate * $itemQty, 2);
        $interHubDriverFee = $route['is_inter_hub'] ? round($rates['inter_hub_driver_fee'], 2) : 0.0;
        $destinationHubFee = $route['is_inter_hub'] ? round($rates['destination_hub_fee'], 2) : 0.0;

        $customDeliveryFee = $delivery?->getAttribute('delivery_fee');
        $lastMileRiderFee = ($customDeliveryFee !== null && (float) $customDeliveryFee > 0)
            ? round((float) $customDeliveryFee, 2)
            : round($rates['last_mile_rider_fee'], 2);

        $commissionInfo = self::commissionForSellerItems($order, $sellerId, $netMerch, $commissionRate);
        $adminCommission = $commissionInfo['amount'];

        // Last-Mile Rider ALWAYS deducts their fee directly from COD cash, even if buyer used Free Shipping!
        $codRiderRetains = round(min($buyerTotalPayable, $lastMileRiderFee), 2);
        $codEscrowRemittance = round(max(0.0, $buyerTotalPayable - $lastMileRiderFee), 2);

        $hubFeesTotal = round($originHubFee + $destinationHubFee, 2);
        $riderFeesTotal = round($pickupRiderFee + $interHubDriverFee + $lastMileRiderFee, 2);
        $merchLogisticsDeducted = round($originHubFee + $pickupRiderFee + $interHubDriverFee + $destinationHubFee, 2);

        // Seller takes all deductions (voucher discounts + waived free shipping + rider/hub fees + admin commission).
        $rawSellerRemaining = round(
            $buyerTotalPayable
            - $lastMileRiderFee
            - $originHubFee
            - $pickupRiderFee
            - $interHubDriverFee
            - $destinationHubFee
            - $adminCommission,
            2
        );

        $sellerEarning = round(max(0.0, $rawSellerRemaining), 2);
        $sellerDeficit = $rawSellerRemaining < 0 ? round(abs($rawSellerRemaining), 2) : 0.0;

        return [
            'has_group'                => true,
            'item_quantity'            => $itemQty,
            'is_inter_hub'             => $route['is_inter_hub'],
            'origin_center_id'         => $route['origin_center_id'],
            'destination_center_id'    => $route['destination_center_id'],
            'gross_merch'              => $grossMerch,
            'merch_discount'           => $merchDiscount,
            'net_merch'                => $netMerch,
            'buyer_shipping_base'      => $buyerShippingBase,
            'shipping_waived'          => $shippingWaived,
            'buyer_shipping_paid'      => $buyerShippingPaid,
            'buyer_total_payable'      => $buyerTotalPayable,
            'origin_hub_fee'           => $originHubFee,
            'pickup_rider_fee'         => $pickupRiderFee,
            'pickup_rider_rate'        => $pickupRiderRate,
            'inter_hub_driver_fee'     => $interHubDriverFee,
            'destination_hub_fee'      => $destinationHubFee,
            'last_mile_rider_fee'      => $lastMileRiderFee,
            'hub_fees_total'           => $hubFeesTotal,
            'rider_fees_total'         => $riderFeesTotal,
            'merch_logistics_deducted' => $merchLogisticsDeducted,
            'commission_rate'          => $commissionInfo['rate'],
            'admin_commission'         => $adminCommission,
            'seller_earning'           => $sellerEarning,
            'seller_deficit'           => $sellerDeficit,
            'cod_rider_retains'        => $codRiderRetains,
            'cod_escrow_remittance'    => $codEscrowRemittance,
        ];
    }

    /**
     * Credit the Pickup Rider (`₱30 × item_quantity`) idempotently when the
     * Origin Hub receives the parcel from the pickup rider.
     */
    public static function creditPickupRiderOnHubReceipt(Delivery $delivery): ?RiderEarning
    {
        try {
            if (! Schema::hasTable('rider_earnings')) {
                return null;
            }
            $pickupRiderId = $delivery->sorting_center_handoff_rider_id ?: $delivery->rider_id;
            if (! $pickupRiderId) {
                return null;
            }

            $order = $delivery->order_id ? $delivery->order()->with('items')->first() : null;
            $sellerId = (int) ($delivery->seller_id ?? $order?->items->pluck('seller_id')->filter()->first() ?? 0);

            $qty = ($order && $sellerId > 0)
                ? self::itemQuantityForSeller($order, $sellerId, $delivery)
                : max(1, (int) $delivery->items()->sum('quantity'));

            $perItem = self::pickupRiderPerItemFee();
            $amount = round($perItem * $qty, 2);

            return RiderEarning::updateOrCreate(
                ['delivery_id' => $delivery->id, 'type' => 'pickup'],
                [
                    'rider_id'    => $pickupRiderId,
                    'amount'      => $amount,
                    'earned_on'   => now()->toDateString(),
                    'description' => "Seller pickup {$delivery->tracking_number} ({$qty} item" . ($qty === 1 ? '' : 's') . ' × ₱' . number_format($perItem, 2) . ')',
                ]
            );
        } catch (\Throwable $e) {
            \Log::warning('Pickup rider earning credit failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Credit the Inter-Hub Transport Driver (`₱20` shared transport)
     * idempotently when the Destination Hub receives an inter-hub transfer.
     */
    public static function creditInterHubDriverOnTransferReceipt(Delivery $delivery): ?RiderEarning
    {
        try {
            if (! Schema::hasTable('rider_earnings') || ! Schema::hasColumn('deliveries', 'inter_hub_rider_id')) {
                return null;
            }
            $driverId = $delivery->inter_hub_rider_id;
            if (! $driverId) {
                return null;
            }

            $amount = self::interHubDriverFee();
            if ($amount <= 0) {
                return null;
            }

            return RiderEarning::updateOrCreate(
                ['delivery_id' => $delivery->id, 'type' => 'inter_hub'],
                [
                    'rider_id'    => $driverId,
                    'amount'      => $amount,
                    'earned_on'   => now()->toDateString(),
                    'description' => "Shared inter-hub transport {$delivery->tracking_number}",
                ]
            );
        } catch (\Throwable $e) {
            \Log::warning('Inter-hub driver earning credit failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Synchronize `EscrowRemittance` rows for every earning recipient of an
     * order so every user/hub/worker who earns has an auditable remittance
     * record tied to the Platform Escrow ledger.
     */
    public static function syncRemittancesForOrder(Order $order, ?PaymentTransaction $tx = null): void
    {
        try {
            if (! Schema::hasTable('escrow_remittances')) {
                return;
            }

            $order->loadMissing(['items', 'orderVouchers.voucher']);
            $tx = $tx ?? Payments::transactionFor($order);

            $isCancelled = $order->status === 'cancelled' || in_array($tx?->status, ['failed', 'refunded'], true);
            $isSettled = $order->payment_status === 'paid' || $tx?->status === 'successful';

            $adminUser = User::where('role', 'admin')->orderBy('id')->first();

            foreach ($order->shippingGroupIds() as $sid) {
                $delivery = self::deliveryForSeller($order, (int) $sid);
                $calc = self::calculateForSeller($order, (int) $sid, $delivery);
                if (! $calc['has_group']) {
                    continue;
                }

                $isDelivered = $delivery && $delivery->status === 'delivered';
                $isCod = $delivery ? Cod::isCod($delivery) : true;

                $originCenter = $calc['origin_center_id'] ? LogisticsCenter::find($calc['origin_center_id']) : null;
                $destCenter = $calc['destination_center_id'] ? LogisticsCenter::find($calc['destination_center_id']) : null;

                $pickupRiderId = $delivery?->sorting_center_handoff_rider_id
                    ?: ($delivery?->picked_up_at ? $delivery->rider_id : null);
                $pickupRider = $pickupRiderId ? Rider::find($pickupRiderId) : null;

                $interHubDriverId = Schema::hasColumn('deliveries', 'inter_hub_rider_id')
                    ? $delivery?->inter_hub_rider_id
                    : null;
                $interHubDriver = $interHubDriverId ? Rider::find($interHubDriverId) : null;

                $lastMileRiderId = ($delivery && ($delivery->received_at || $delivery->status === 'delivered' || ! $pickupRiderId))
                    ? $delivery->rider_id
                    : null;
                $lastMileRider = $lastMileRiderId ? Rider::find($lastMileRiderId) : null;

                $originManager = $originCenter
                    ? User::where('center_id', $originCenter->id)->whereIn('role', ['logistics_admin', 'logistics_staff', 'staff'])->orderBy('id')->first()
                    : null;
                $destManager = $destCenter
                    ? User::where('center_id', $destCenter->id)->whereIn('role', ['logistics_admin', 'logistics_staff', 'staff'])->orderBy('id')->first()
                    : null;

                $sellerProfile = Seller::where('user_id', $sid)->first();
                $sellerDisplay = $sellerProfile?->display_name ?: (User::find($sid)?->name ?? "Seller #{$sid}");

                $rows = [
                    EscrowRemittance::ROLE_ORIGIN_HUB => [
                        'amount'              => $calc['origin_hub_fee'],
                        'recipient_user_id'   => $originManager?->id,
                        'rider_id'            => null,
                        'logistics_center_id' => $originCenter?->id,
                        'recipient_name'      => $originCenter ? $originCenter->name : 'Origin Logistics Hub (Pending Routing)',
                        'rule_applied'        => '₱' . number_format($calc['origin_hub_fee'], 2) . ' per seller shipment',
                        'settlement_mode'     => 'escrow_bank_remittance',
                        'earned_ready'        => (bool) ($delivery?->received_at || $isDelivered),
                    ],
                    EscrowRemittance::ROLE_PICKUP_RIDER => [
                        'amount'              => $calc['pickup_rider_fee'],
                        'recipient_user_id'   => $pickupRider?->user_id,
                        'rider_id'            => $pickupRider?->id,
                        'logistics_center_id' => $pickupRider?->center_id ?? $originCenter?->id,
                        'recipient_name'      => $pickupRider ? $pickupRider->name : 'Pickup Rider (Pending Assignment)',
                        'rule_applied'        => '₱' . number_format($calc['pickup_rider_rate'], 2) . ' × ' . $calc['item_quantity'] . ' item(s)',
                        'settlement_mode'     => 'escrow_bank_remittance',
                        'earned_ready'        => (bool) ($delivery?->received_at || $isDelivered),
                    ],
                    EscrowRemittance::ROLE_INTER_HUB_DRIVER => [
                        'amount'              => $calc['inter_hub_driver_fee'],
                        'recipient_user_id'   => $interHubDriver?->user_id ?? $originManager?->id,
                        'rider_id'            => $interHubDriver?->id,
                        'logistics_center_id' => $originCenter?->id,
                        'recipient_name'      => $calc['is_inter_hub']
                            ? ($interHubDriver ? $interHubDriver->name : (($originCenter?->name ?? 'Origin Hub') . ' Shared Linehaul Pool'))
                            : 'Not applicable (Same-hub local route)',
                        'rule_applied'        => $calc['is_inter_hub']
                            ? '₱' . number_format($calc['inter_hub_driver_fee'], 2) . ' shared transport (' . ($originCenter?->name ?? 'Origin') . ' → ' . ($destCenter?->name ?? 'Destination') . ')'
                            : '₱0.00 (Origin Hub = Destination Hub)',
                        'settlement_mode'     => 'escrow_bank_remittance',
                        'earned_ready'        => $calc['is_inter_hub'] && ($isDelivered || ($delivery && (int) $delivery->center_id === (int) $calc['destination_center_id'])),
                    ],
                    EscrowRemittance::ROLE_DESTINATION_HUB => [
                        'amount'              => $calc['destination_hub_fee'],
                        'recipient_user_id'   => $destManager?->id,
                        'rider_id'            => null,
                        'logistics_center_id' => $calc['is_inter_hub'] ? $destCenter?->id : null,
                        'recipient_name'      => $calc['is_inter_hub']
                            ? ($destCenter?->name ?? 'Destination Logistics Hub')
                            : 'Same as Origin Hub (No duplicate charge)',
                        'rule_applied'        => $calc['is_inter_hub']
                            ? '₱' . number_format($calc['destination_hub_fee'], 2) . ' per seller shipment'
                            : '₱0.00 (Local single-hub shipment)',
                        'settlement_mode'     => 'escrow_bank_remittance',
                        'earned_ready'        => $calc['is_inter_hub'] && ($isDelivered || ($delivery && (int) $delivery->center_id === (int) $calc['destination_center_id'])),
                    ],
                    EscrowRemittance::ROLE_LAST_MILE_RIDER => [
                        'amount'              => $calc['last_mile_rider_fee'],
                        'recipient_user_id'   => $lastMileRider?->user_id,
                        'rider_id'            => $lastMileRider?->id,
                        'logistics_center_id' => $lastMileRider?->center_id ?? $destCenter?->id ?? $originCenter?->id,
                        'recipient_name'      => $lastMileRider ? $lastMileRider->name : 'Last-Mile Delivery Rider (Pending)',
                        'rule_applied'        => $isCod
                            ? '₱' . number_format($calc['last_mile_rider_fee'], 2) . ' per shipment (deducted directly from COD)'
                            : '₱' . number_format($calc['last_mile_rider_fee'], 2) . ' per seller shipment',
                        'settlement_mode'     => $isCod ? 'cod_retained' : 'escrow_bank_remittance',
                        'earned_ready'        => $isDelivered,
                        'auto_remitted'       => $isDelivered && $isCod,
                    ],
                    EscrowRemittance::ROLE_ADMIN => [
                        'amount'              => $calc['admin_commission'],
                        'recipient_user_id'   => $adminUser?->id,
                        'rider_id'            => null,
                        'logistics_center_id' => null,
                        'recipient_name'      => 'Invoiz Platform Admin',
                        'rule_applied'        => number_format($calc['commission_rate'], 2) . '% commission on ₱' . number_format($calc['net_merch'], 2) . ' net merchandise',
                        'settlement_mode'     => 'platform_retained',
                        'earned_ready'        => $isSettled,
                        'auto_remitted'       => $isSettled,
                    ],
                    EscrowRemittance::ROLE_SELLER => [
                        'amount'              => $calc['seller_earning'],
                        'recipient_user_id'   => (int) $sid,
                        'rider_id'            => null,
                        'logistics_center_id' => null,
                        'recipient_name'      => $sellerDisplay,
                        'rule_applied'        => 'Remaining product earnings after deductions'
                            . ($calc['shipping_waived'] > 0 ? ' (incl. ₱' . number_format($calc['shipping_waived'], 2) . ' free-shipping absorbed)' : ''),
                        'settlement_mode'     => 'seller_wallet_credit',
                        'earned_ready'        => $isSettled,
                        'auto_remitted'       => $isSettled,
                    ],
                ];

                $codCollected = ! $isCod || (bool) ($delivery && $delivery->cod_amount_received !== null && (float) $delivery->cod_amount_received > 0);

                foreach ($rows as $role => $meta) {
                    $existing = EscrowRemittance::where('order_id', $order->id)
                        ->where('seller_id', $sid)
                        ->where('recipient_role', $role)
                        ->first();

                    // Do not overwrite an already-remitted or already-accepted row unless the order was cancelled/voided.
                    if ($isCancelled) {
                        $status = 'voided';
                    } elseif ($existing && in_array($existing->status, ['remitted', 'accepted'], true)) {
                        $status = $existing->status;
                    } elseif (! empty($meta['auto_remitted']) && $codCollected) {
                        $status = 'remitted';
                    } elseif ((float) $meta['amount'] <= 0.0) {
                        $status = ($isSettled && $codCollected) ? 'remitted' : 'held_in_escrow';
                    } elseif (($isSettled || ! empty($meta['earned_ready'])) && $isDelivered && $codCollected) {
                        $status = 'pending_remittance';
                    } else {
                        $status = 'held_in_escrow';
                    }

                    $remittedAt = $existing?->remitted_at;
                    $bankRef = $existing?->bank_reference;
                    if ($status === 'remitted' && ! $remittedAt) {
                        $remittedAt = now();
                        $bankRef = $bankRef ?: match ($meta['settlement_mode']) {
                            'cod_retained'         => 'COD-DEDUCT-' . ($delivery?->tracking_number ?? $order->id),
                            'platform_retained'    => 'ESCROW-COMM-' . $order->id,
                            'seller_wallet_credit' => 'SELLER-SETTLED-' . $order->id,
                            default                => 'ESCROW-' . $order->id,
                        };
                    }

                    EscrowRemittance::updateOrCreate(
                        [
                            'order_id'       => $order->id,
                            'seller_id'      => (int) $sid,
                            'recipient_role' => $role,
                        ],
                        [
                            'delivery_id'            => $delivery?->id,
                            'payment_transaction_id' => $tx?->id,
                            'recipient_user_id'      => $meta['recipient_user_id'],
                            'rider_id'               => $meta['rider_id'],
                            'logistics_center_id'    => $meta['logistics_center_id'],
                            'recipient_name'         => $meta['recipient_name'],
                            'rule_applied'           => $meta['rule_applied'],
                            'amount'                 => round((float) $meta['amount'], 2),
                            'settlement_mode'        => $meta['settlement_mode'],
                            'status'                 => $status,
                            'bank_reference'         => $bankRef,
                            'remitted_at'            => $remittedAt,
                        ]
                    );
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('EscrowRemittance sync failed: ' . $e->getMessage());
        }
    }
}


<?php

namespace App\Support;

use App\Models\CommissionRate;
use App\Models\Delivery;
use App\Models\EscrowRemittance;
use App\Models\HubTransfer;
use App\Models\HubTransferEarning;
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
 * Single source of truth for the logistics stakeholder allocation.
 *
 * Recipients (per seller shipment):
 *   Origin Hub            flat, always
 *   Pickup Rider          rate x item quantity
 *   Inter-Hub Driver      flat, once per shipment for the whole route
 *   Destination Hub       flat, only when the route really changes hubs
 *   Last-Mile Rider       flat, on the completed last-mile delivery
 *   Platform Admin        configured commission on net merchandise
 *   Seller                remainder (may be 0 with an explicit deficit)
 *
 * Route facts are derived from the authoritative fleet records
 * (hub_transfers + hub_transfer_parcels), never from a delivery-level
 * rider/linehaul shortcut.
 */
class EscrowEarnings
{
    public const SETTING_KEYS = [
        'origin_hub_fee' => 'earnings_origin_hub_fee',
        'pickup_rider_per_item' => 'earnings_pickup_rider_per_item',
        'inter_hub_driver_fee' => 'earnings_inter_hub_driver_fee',
        'destination_hub_fee' => 'earnings_destination_hub_fee',
        'last_mile_rider_fee' => 'earnings_last_mile_rider_fee',
    ];

    /** @return array<string, float> */
    public static function rates(): array
    {
        $rates = [
            'origin_hub_fee' => round((float) config('earnings.origin_hub_fee', 30.00), 2),
            'pickup_rider_per_item' => round((float) config('earnings.pickup_rider_per_item', 30.00), 2),
            'inter_hub_driver_fee' => round((float) config('earnings.inter_hub_driver_fee', 20.00), 2),
            'destination_hub_fee' => round((float) config('earnings.destination_hub_fee', 30.00), 2),
            'last_mile_rider_fee' => round((float) config('earnings.last_mile_rider_fee', 50.00), 2),
        ];

        try {
            if (Schema::hasTable('settings')) {
                $stored = Setting::whereIn('key', array_values(self::SETTING_KEYS))->pluck('value', 'key');
                foreach (self::SETTING_KEYS as $property => $settingKey) {
                    if ($stored->has($settingKey) && is_numeric($stored->get($settingKey))) {
                        $rates[$property] = round(max(0.0, (float) $stored->get($settingKey)), 2);
                    }
                }
            }
        } catch (\Throwable $e) {
            // Schema-less contexts fall back to config defaults.
        }

        $rates['admin_commission_rate'] = Payments::defaultCommissionRate();

        return $rates;
    }

    public static function updateRates(array $values): void
    {
        foreach (self::SETTING_KEYS as $property => $settingKey) {
            if (array_key_exists($property, $values) && is_numeric($values[$property])) {
                Setting::set($settingKey, number_format(max(0.0, (float) $values[$property]), 2, '.', ''));
            }
        }
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

    public static function lastMileRiderFee(): float
    {
        return self::rates()['last_mile_rider_fee'];
    }

    public static function deliveryForSeller(Order $order, int $sellerId): ?Delivery
    {
        try {
            if ($order->relationLoaded('deliveries')) {
                $found = $order->deliveries->first(fn ($delivery) => (int) $delivery->seller_id === $sellerId)
                    ?? ($order->deliveries->count() === 1 ? $order->deliveries->first() : null);
                if ($found) {
                    return $found;
                }
            }
            if ($order->relationLoaded('delivery') && $order->delivery
                && (int) ($order->delivery->seller_id ?? $sellerId) === $sellerId) {
                return $order->delivery;
            }
            if ($order->id && Schema::hasTable('deliveries')) {
                return Delivery::where('order_id', $order->id)
                    ->where(fn ($query) => $query->where('seller_id', $sellerId)->orWhereNull('seller_id'))
                    ->orderByRaw('CASE WHEN seller_id = ? THEN 0 ELSE 1 END', [$sellerId])
                    ->orderBy('id')
                    ->first();
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    /**
     * Authoritative transfer legs for a delivery, oldest first.
     *
     * @return \Illuminate\Support\Collection<int, HubTransfer>
     */
    public static function transferLegs(Delivery $delivery)
    {
        try {
            if (! $delivery->id
                || ! Schema::hasTable('hub_transfers')
                || ! Schema::hasTable('hub_transfer_parcels')) {
                return collect();
            }

            return HubTransfer::query()
                ->whereHas('parcels', fn ($query) => $query->where('delivery_id', $delivery->id))
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /**
     * @return array{origin_center_id: ?int, destination_center_id: ?int, is_inter_hub: bool, inter_hub_completed: bool}
     */
    public static function resolveHubRoute(?Delivery $delivery): array
    {
        if (! $delivery) {
            return [
                'origin_center_id' => null,
                'destination_center_id' => null,
                'is_inter_hub' => false,
                'inter_hub_completed' => false,
            ];
        }

        $legs = self::transferLegs($delivery);
        $originId = $legs->isNotEmpty()
            ? ($legs->first()->origin_center_id ?: $delivery->center_id)
            : ($delivery->center_id ?: null);
        $destinationId = $legs->isNotEmpty()
            ? ($legs->last()->destination_center_id ?: $delivery->destination_center_id)
            : ($delivery->destination_center_id ?: $delivery->center_id);

        $originId = $originId !== null ? (int) $originId : null;
        $destinationId = $destinationId !== null ? (int) $destinationId : null;
        $isInterHub = $originId !== null && $destinationId !== null && $originId !== $destinationId;

        return [
            'origin_center_id' => $originId,
            'destination_center_id' => $destinationId,
            'is_inter_hub' => $isInterHub,
            'inter_hub_completed' => $isInterHub
                && $legs->contains(fn (HubTransfer $leg) => $leg->status === 'received'),
        ];
    }

    public static function itemQuantityForSeller(Order $order, int $sellerId, ?Delivery $delivery = null): int
    {
        $items = $order->relationLoaded('items')
            ? $order->items->where('seller_id', $sellerId)
            : $order->items()->where('seller_id', $sellerId)->get();

        $quantity = (int) $items->sum(fn ($item) => max(0, (int) ($item->quantity ?? 0)));

        if ($quantity <= 0 && $delivery) {
            try {
                $deliveryItems = $delivery->relationLoaded('items') ? $delivery->items : $delivery->items()->get();
                $quantity = (int) $deliveryItems->sum(fn ($item) => max(0, (int) ($item->quantity ?? 0)));
            } catch (\Throwable $e) {
            }
        }

        return max(1, $quantity);
    }

    /** @return array{rate: float, amount: float} */
    public static function commissionForSellerItems(Order $order, int $sellerId, float $netMerch, ?float $overrideRate = null): array
    {
        $defaultRate = $overrideRate ?? Payments::defaultCommissionRate();
        if ($netMerch <= 0) {
            return ['rate' => $defaultRate, 'amount' => 0.0];
        }
        if ($overrideRate !== null) {
            return ['rate' => $defaultRate, 'amount' => round($netMerch * $defaultRate / 100, 2)];
        }

        try {
            if (! Schema::hasTable('commission_rates') || ! Schema::hasTable('products')) {
                return ['rate' => $defaultRate, 'amount' => round($netMerch * $defaultRate / 100, 2)];
            }

            $categoryRates = CommissionRate::whereNotNull('category_id')->pluck('rate', 'category_id');
            if ($categoryRates->isEmpty()) {
                return ['rate' => $defaultRate, 'amount' => round($netMerch * $defaultRate / 100, 2)];
            }

            $items = $order->relationLoaded('items')
                ? $order->items->where('seller_id', $sellerId)
                : $order->items()->where('seller_id', $sellerId)->get();
            $grossTotal = (float) $items->sum(fn ($item) => $item->lineTotal
                ?? ((float) ($item->unit_price ?? $item->price ?? 0)) * ((int) ($item->quantity ?? 0)));
            if ($grossTotal <= 0) {
                return ['rate' => $defaultRate, 'amount' => round($netMerch * $defaultRate / 100, 2)];
            }

            $productCategories = \App\Models\Product::withTrashed()
                ->whereIn('id', $items->pluck('product_id')->filter()->unique()->all())
                ->pluck('category_id', 'id');

            $weighted = 0.0;
            foreach ($items as $item) {
                $line = $item->lineTotal ?? ((float) ($item->unit_price ?? $item->price ?? 0)) * ((int) ($item->quantity ?? 0));
                $lineNet = $netMerch * ($line / $grossTotal);
                $categoryId = $productCategories[$item->product_id] ?? null;
                $itemRate = ($categoryId !== null && $categoryRates->has($categoryId))
                    ? (float) $categoryRates->get($categoryId)
                    : $defaultRate;
                $weighted += $lineNet * ($itemRate / 100);
            }

            $amount = round($weighted, 2);
            $effectiveRate = round(($amount / $netMerch) * 100, 2);

            return ['rate' => $effectiveRate, 'amount' => $amount];
        } catch (\Throwable $e) {
            return ['rate' => $defaultRate, 'amount' => round($netMerch * $defaultRate / 100, 2)];
        }
    }

    public static function calculateForSeller(Order $order, int $sellerId, ?Delivery $delivery = null, ?float $commissionRate = null): array
    {
        $order->loadMissing(['items', 'orderVouchers.voucher']);
        $rates = self::rates();

        $hasGroup = $order->items->where('seller_id', $sellerId)->isNotEmpty();
        if (! $hasGroup) {
            return array_merge([
                'has_group' => false,
                'item_quantity' => 0,
                'is_inter_hub' => false,
                'inter_hub_completed' => false,
                'origin_center_id' => null,
                'destination_center_id' => null,
                'gross_merch' => 0.0,
                'merch_discount' => 0.0,
                'net_merch' => 0.0,
                'buyer_shipping_base' => 0.0,
                'shipping_waived' => 0.0,
                'buyer_shipping_paid' => 0.0,
                'buyer_total_payable' => 0.0,
                'origin_hub_fee' => 0.0,
                'pickup_rider_fee' => 0.0,
                'pickup_rider_rate' => 0.0,
                'inter_hub_driver_fee' => 0.0,
                'destination_hub_fee' => 0.0,
                'last_mile_rider_fee' => 0.0,
                'hub_fees_total' => 0.0,
                'rider_fees_total' => 0.0,
                'merch_logistics_deducted' => 0.0,
                'commission_rate' => $commissionRate ?? $rates['admin_commission_rate'],
                'admin_commission' => 0.0,
                'seller_earning' => 0.0,
                'seller_deficit' => 0.0,
                'cod_rider_retains' => 0.0,
                'cod_escrow_remittance' => 0.0,
            ], self::conservation([]));
        }

        $delivery ??= self::deliveryForSeller($order, $sellerId);
        $route = self::resolveHubRoute($delivery);
        $itemQuantity = self::itemQuantityForSeller($order, $sellerId, $delivery);

        $grossMerch = round($order->sellerSubtotal($sellerId), 2);
        $merchDiscount = round($order->voucherDiscountForSeller($sellerId), 2);
        $netMerch = round(max(0.0, $grossMerch - $merchDiscount), 2);

        // Buyer shipping uses the same flat fee the checkout charges; the
        // last-mile rider rate happens to match it, but they stay separate so
        // either can be re-configured without breaking the other.
        $buyerShippingBase = round((float) Order::SHIPPING_FEE_PER_SELLER, 2);
        $shippingWaived = round($order->shippingWaivedForSeller($sellerId), 2);
        $buyerShippingPaid = round(max(0.0, $buyerShippingBase - $shippingWaived), 2);
        $buyerTotalPayable = round($netMerch + $buyerShippingPaid, 2);

        $originHubFee = round($rates['origin_hub_fee'], 2);
        $pickupRiderRate = round($rates['pickup_rider_per_item'], 2);
        $pickupRiderFee = round($pickupRiderRate * $itemQuantity, 2);
        $interHubDriverFee = $route['inter_hub_completed'] ? round($rates['inter_hub_driver_fee'], 2) : 0.0;
        $destinationHubFee = $route['inter_hub_completed'] ? round($rates['destination_hub_fee'], 2) : 0.0;

        $customDeliveryFee = $delivery?->getAttribute('delivery_fee');
        $lastMileRiderFee = ($customDeliveryFee !== null && (float) $customDeliveryFee > 0)
            ? round((float) $customDeliveryFee, 2)
            : round($rates['last_mile_rider_fee'], 2);

        $commission = self::commissionForSellerItems($order, $sellerId, $netMerch, $commissionRate);
        $adminCommission = round($commission['amount'], 2);

        $codRiderRetains = round(min($buyerTotalPayable, $lastMileRiderFee), 2);
        $codEscrowRemittance = round(max(0.0, $buyerTotalPayable - $lastMileRiderFee), 2);

        $hubFeesTotal = round($originHubFee + $destinationHubFee, 2);
        $riderFeesTotal = round($pickupRiderFee + $interHubDriverFee + $lastMileRiderFee, 2);
        $merchLogisticsDeducted = round($originHubFee + $pickupRiderFee + $interHubDriverFee + $destinationHubFee, 2);

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

        $calc = [
            'has_group' => true,
            'item_quantity' => $itemQuantity,
            'is_inter_hub' => $route['is_inter_hub'],
            'inter_hub_completed' => $route['inter_hub_completed'],
            'origin_center_id' => $route['origin_center_id'],
            'destination_center_id' => $route['destination_center_id'],
            'gross_merch' => $grossMerch,
            'merch_discount' => $merchDiscount,
            'net_merch' => $netMerch,
            'buyer_shipping_base' => $buyerShippingBase,
            'shipping_waived' => $shippingWaived,
            'buyer_shipping_paid' => $buyerShippingPaid,
            'buyer_total_payable' => $buyerTotalPayable,
            'origin_hub_fee' => $originHubFee,
            'pickup_rider_fee' => $pickupRiderFee,
            'pickup_rider_rate' => $pickupRiderRate,
            'inter_hub_driver_fee' => $interHubDriverFee,
            'destination_hub_fee' => $destinationHubFee,
            'last_mile_rider_fee' => $lastMileRiderFee,
            'hub_fees_total' => $hubFeesTotal,
            'rider_fees_total' => $riderFeesTotal,
            'merch_logistics_deducted' => $merchLogisticsDeducted,
            'commission_rate' => $commission['rate'],
            'admin_commission' => $adminCommission,
            'seller_earning' => $sellerEarning,
            'seller_deficit' => $sellerDeficit,
            'cod_rider_retains' => $codRiderRetains,
            'cod_escrow_remittance' => $codEscrowRemittance,
        ];

        return array_merge($calc, self::conservation($calc));
    }

    /**
     * Two explicit identities, so a shortfall is never hidden:
     *   1. buyer paid + seller deficit = every stakeholder allocation
     *   2. gross merchandise + gross shipping = buyer paid + absorbed vouchers
     *
     * @param  array<string, float|int|bool|null>  $calc
     * @return array<string, float|bool>
     */
    public static function conservation(array $calc): array
    {
        $seller = round((float) ($calc['seller_earning'] ?? 0), 2);
        $lastMile = round((float) ($calc['last_mile_rider_fee'] ?? 0), 2);
        $origin = round((float) ($calc['origin_hub_fee'] ?? 0), 2);
        $pickup = round((float) ($calc['pickup_rider_fee'] ?? 0), 2);
        $interHub = round((float) ($calc['inter_hub_driver_fee'] ?? 0), 2);
        $destination = round((float) ($calc['destination_hub_fee'] ?? 0), 2);
        $admin = round((float) ($calc['admin_commission'] ?? 0), 2);
        $deficit = round((float) ($calc['seller_deficit'] ?? 0), 2);
        $buyerPaid = round((float) ($calc['buyer_total_payable'] ?? 0), 2);
        $grossMerch = round((float) ($calc['gross_merch'] ?? 0), 2);
        $shippingBase = round((float) ($calc['buyer_shipping_base'] ?? 0), 2);
        $absorbed = round((float) ($calc['merch_discount'] ?? 0) + (float) ($calc['shipping_waived'] ?? 0), 2);

        $allocations = round($seller + $lastMile + $origin + $pickup + $interHub + $destination + $admin, 2);
        $allocationDifference = round($buyerPaid + $deficit - $allocations, 2);
        $voucherDifference = round($grossMerch + $shippingBase - ($buyerPaid + $absorbed), 2);

        return [
            'allocations_total' => $allocations,
            'absorbed_vouchers' => $absorbed,
            'allocation_difference' => $allocationDifference,
            'voucher_difference' => $voucherDifference,
            'conserved' => abs($allocationDifference) < 0.01 && abs($voucherDifference) < 0.01,
        ];
    }

    /** Pickup rider already credited at origin receipt, if any. */
    public static function pickupRiderIdFor(Delivery $delivery): ?int
    {
        try {
            if (Schema::hasTable('rider_earnings')) {
                $earning = RiderEarning::where('delivery_id', $delivery->id)->where('type', 'pickup')->first();
                if ($earning?->rider_id) {
                    return (int) $earning->rider_id;
                }
            }
        } catch (\Throwable $e) {
        }

        $handoff = $delivery->sorting_center_handoff_rider_id;
        if ($handoff) {
            return (int) $handoff;
        }

        return $delivery->picked_up_at && $delivery->rider_id ? (int) $delivery->rider_id : null;
    }

    public static function interHubEarningFor(Delivery $delivery): ?HubTransferEarning
    {
        try {
            if (! Schema::hasTable('hub_transfer_earnings')) {
                return null;
            }

            return HubTransferEarning::where('delivery_id', $delivery->id)
                ->where('recipient_role', HubTransferEarning::ROLE_INTER_HUB_DRIVER)
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Credit the pickup rider (rate x item quantity) once, when the origin hub
     * receives the parcel from that rider.
     */
    public static function creditPickupRiderOnHubReceipt(Delivery $delivery): ?RiderEarning
    {
        try {
            if (! Schema::hasTable('rider_earnings')) {
                return null;
            }
            // A parcel already inside the transfer network was credited at the
            // first hub receipt; never credit again at a later hub.
            if (self::transferLegs($delivery)->isNotEmpty()) {
                return null;
            }
            if (! $delivery->received_at && $delivery->parcel_status !== 'received') {
                return null;
            }

            $pickupRiderId = $delivery->sorting_center_handoff_rider_id ?: $delivery->rider_id;
            if (! $pickupRiderId) {
                return null;
            }

            $order = $delivery->order_id ? $delivery->order()->with('items')->first() : null;
            $sellerId = (int) ($delivery->seller_id ?? $order?->items->pluck('seller_id')->filter()->first() ?? 0);
            $quantity = ($order && $sellerId > 0)
                ? self::itemQuantityForSeller($order, $sellerId, $delivery)
                : max(1, (int) $delivery->items()->sum('quantity'));

            $perItem = self::pickupRiderPerItemFee();
            $amount = round($perItem * $quantity, 2);

            $earning = RiderEarning::updateOrCreate(
                ['delivery_id' => $delivery->id, 'type' => 'pickup'],
                [
                    'rider_id' => $pickupRiderId,
                    'amount' => $amount,
                    'earned_on' => now()->toDateString(),
                    'description' => "Seller pickup {$delivery->tracking_number} ({$quantity} item"
                        .($quantity === 1 ? '' : 's').' x PHP '.number_format($perItem, 2).')',
                ]
            );

            return $earning instanceof RiderEarning ? $earning : null;
        } catch (\Throwable $e) {
            \Log::warning('Pickup rider earning credit failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Credit the Hub Transport Driver once per seller shipment for the entire
     * route. Called when a transfer's parcels are received; later legs reuse
     * the same row keyed by (delivery_id, role). Supports both HubTransfer-based
     * fleet manifests and Delivery linehaul transfers.
     */
    public static function creditInterHubDriverOnTransferReceipt(HubTransfer|Delivery $transferOrDelivery, ?Delivery $delivery = null): mixed
    {
        try {
            if ($transferOrDelivery instanceof Delivery) {
                $delivery = $transferOrDelivery;
                $activeParcel = $delivery->incomingTransferParcel ?? $delivery->hubTransferParcels()->latest('id')->first();
                if ($activeParcel && $activeParcel->transfer) {
                    return self::creditInterHubDriverOnTransferReceipt($activeParcel->transfer, $delivery);
                }

                if (! Schema::hasTable('rider_earnings') || ! Schema::hasColumn('deliveries', 'inter_hub_rider_id')) {
                    return null;
                }
                $driverId = $delivery->inter_hub_rider_id;
                if (! $driverId) {
                    return null;
                }

                $keys = [
                    'delivery_id' => $delivery->id,
                    'rider_id' => $driverId,
                    'type' => 'inter_hub_transfer',
                ];
                $existing = RiderEarning::where($keys)->first();
                if ($existing) {
                    return $existing;
                }

                $fee = self::interHubDriverFee();
                if ($fee <= 0) {
                    return null;
                }

                return RiderEarning::create([
                    'delivery_id' => $delivery->id,
                    'rider_id' => $driverId,
                    'amount' => $fee,
                    'type' => 'inter_hub_transfer',
                    'description' => "Inter-hub transfer linehaul pay for tracking {$delivery->tracking_number}",
                    'earned_on' => now()->toDateString(),
                ]);
            }

            $transfer = $transferOrDelivery;
            if (! $delivery) {
                return null;
            }

            if (! Schema::hasTable('hub_transfer_earnings')) {
                return null;
            }
            if (! $transfer->driver_id || ! $transfer->logistics_company_id) {
                return null;
            }

            $amount = self::interHubDriverFee();
            if ($amount <= 0) {
                return null;
            }

            $keys = [
                'delivery_id' => $delivery->id,
                'recipient_role' => HubTransferEarning::ROLE_INTER_HUB_DRIVER,
            ];
            // One ₱20 credit for the whole route: a later leg must never
            // reassign or duplicate the driver's earning.
            $existing = HubTransferEarning::where($keys)->first();
            if ($existing) {
                return $existing;
            }

            return HubTransferEarning::create($keys + [
                'logistics_company_id' => $transfer->logistics_company_id,
                'hub_transfer_id' => $transfer->id,
                'hub_transport_driver_id' => $transfer->driver_id,
                'amount' => $amount,
                'earned_on' => now()->toDateString(),
                'description' => "Inter-hub transport {$delivery->tracking_number} (one fee per shipment for the full route)",
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Inter-hub driver earning credit failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Synchronize the seven escrow remittance rows for every seller group.
     * Rows already remitted are never rewritten; cancelled/failed orders void
     * them. The method is idempotent and safe to call repeatedly.
     */
    public static function syncRemittancesForOrder(Order $order, ?PaymentTransaction $tx = null): void
    {
        try {
            if (! Schema::hasTable('escrow_remittances')) {
                return;
            }

            $order->loadMissing(['items', 'orderVouchers.voucher']);
            $tx ??= Payments::transactionFor($order);

            $isCancelled = $order->status === 'cancelled' || in_array($tx?->status, ['failed', 'refunded'], true);
            $isSettled = $order->payment_status === 'paid' || $tx?->status === 'successful';
            $adminUser = User::where('role', 'admin')->orderBy('id')->first();

            foreach ($order->shippingGroupIds() as $sellerId) {
                $sellerId = (int) $sellerId;
                $delivery = self::deliveryForSeller($order, $sellerId);
                $calc = self::calculateForSeller($order, $sellerId, $delivery);
                if (! $calc['has_group']) {
                    continue;
                }

                $legs = $delivery ? self::transferLegs($delivery) : collect();
                $completedLeg = $legs->first(fn (HubTransfer $leg) => $leg->status === 'received');
                $isDelivered = $delivery && $delivery->status === 'delivered';
                $isCod = $delivery ? Cod::isCod($delivery) : true;

                $originCenter = $calc['origin_center_id'] ? LogisticsCenter::find($calc['origin_center_id']) : null;
                $destinationCenter = $calc['destination_center_id'] ? LogisticsCenter::find($calc['destination_center_id']) : null;

                $pickupRiderId = $delivery ? self::pickupRiderIdFor($delivery) : null;
                $pickupRider = $pickupRiderId ? Rider::find($pickupRiderId) : null;

                $interHubEarning = $delivery ? self::interHubEarningFor($delivery) : null;
                $interHubDriverId = $interHubEarning?->hub_transport_driver_id
                    ?: $completedLeg?->driver_id;
                $interHubDriver = $interHubDriverId
                    ? \App\Models\HubTransportDriver::find($interHubDriverId)
                    : null;
                $interHubTransfer = $interHubEarning?->hub_transfer_id
                    ? HubTransfer::find($interHubEarning->hub_transfer_id)
                    : $completedLeg;

                $lastMileRiderId = ($delivery && ($delivery->received_at || $delivery->status === 'delivered' || ! $pickupRiderId))
                    ? $delivery->rider_id
                    : null;
                $lastMileRider = $lastMileRiderId ? Rider::find($lastMileRiderId) : null;

                $originManager = $originCenter
                    ? User::where('center_id', $originCenter->id)
                        ->whereIn('role', ['logistics_admin', 'logistics_staff', 'staff'])
                        ->orderBy('id')->first()
                    : null;
                $destinationManager = $destinationCenter
                    ? User::where('center_id', $destinationCenter->id)
                        ->whereIn('role', ['logistics_admin', 'logistics_staff', 'staff'])
                        ->orderBy('id')->first()
                    : null;

                $sellerProfile = Seller::where('user_id', $sellerId)->first();
                $sellerDisplay = $sellerProfile?->display_name ?: (User::find($sellerId)?->name ?? "Seller #{$sellerId}");
                $routeReady = $calc['inter_hub_completed'] && $delivery
                    && ($isDelivered || (int) $delivery->center_id === (int) $calc['destination_center_id']);

                $rows = [
                    EscrowRemittance::ROLE_ORIGIN_HUB => [
                        'amount' => $calc['origin_hub_fee'],
                        'recipient_user_id' => $originManager?->id,
                        'rider_id' => null,
                        'hub_transfer_id' => null,
                        'hub_transport_driver_id' => null,
                        'logistics_center_id' => $originCenter?->id,
                        'recipient_name' => $originCenter?->name ?? 'Origin Logistics Hub (Pending Routing)',
                        'rule_applied' => 'PHP '.number_format($calc['origin_hub_fee'], 2).' per seller shipment',
                        'settlement_mode' => 'escrow_bank_remittance',
                        'earned_ready' => (bool) ($delivery?->received_at || $isDelivered),
                    ],
                    EscrowRemittance::ROLE_PICKUP_RIDER => [
                        'amount' => $calc['pickup_rider_fee'],
                        'recipient_user_id' => $pickupRider?->user_id,
                        'rider_id' => $pickupRider?->id,
                        'hub_transfer_id' => null,
                        'hub_transport_driver_id' => null,
                        'logistics_center_id' => $pickupRider?->center_id ?? $originCenter?->id,
                        'recipient_name' => $pickupRider?->name ?? 'Pickup Rider (Pending Assignment)',
                        'rule_applied' => 'PHP '.number_format($calc['pickup_rider_rate'], 2).' x '.$calc['item_quantity'].' item(s)',
                        'settlement_mode' => 'escrow_bank_remittance',
                        'earned_ready' => (bool) ($delivery?->received_at || $isDelivered),
                    ],
                    EscrowRemittance::ROLE_INTER_HUB_DRIVER => [
                        'amount' => $calc['inter_hub_driver_fee'],
                        'recipient_user_id' => null,
                        'rider_id' => null,
                        'hub_transfer_id' => $interHubTransfer?->id,
                        'hub_transport_driver_id' => $interHubDriver?->id,
                        'logistics_center_id' => $originCenter?->id,
                        'recipient_name' => $calc['inter_hub_completed']
                            ? ($interHubDriver?->name ?? (($originCenter?->name ?? 'Origin Hub').' Shared Linehaul Pool'))
                            : 'Not applicable (Same-hub local route)',
                        'rule_applied' => $calc['inter_hub_completed']
                            ? 'PHP '.number_format($calc['inter_hub_driver_fee'], 2).' once per seller shipment for the full route'
                            : 'PHP 0.00 (no completed inter-hub route)',
                        'settlement_mode' => 'escrow_bank_remittance',
                        'earned_ready' => (bool) $routeReady,
                    ],
                    EscrowRemittance::ROLE_DESTINATION_HUB => [
                        'amount' => $calc['destination_hub_fee'],
                        'recipient_user_id' => $destinationManager?->id,
                        'rider_id' => null,
                        'hub_transfer_id' => null,
                        'hub_transport_driver_id' => null,
                        'logistics_center_id' => $calc['inter_hub_completed'] ? $destinationCenter?->id : null,
                        'recipient_name' => $calc['inter_hub_completed']
                            ? ($destinationCenter?->name ?? 'Destination Logistics Hub')
                            : 'Same as Origin Hub (No duplicate charge)',
                        'rule_applied' => $calc['inter_hub_completed']
                            ? 'PHP '.number_format($calc['destination_hub_fee'], 2).' per seller shipment'
                            : 'PHP 0.00 (Local single-hub shipment)',
                        'settlement_mode' => 'escrow_bank_remittance',
                        'earned_ready' => (bool) $routeReady,
                    ],
                    EscrowRemittance::ROLE_LAST_MILE_RIDER => [
                        'amount' => $calc['last_mile_rider_fee'],
                        'recipient_user_id' => $lastMileRider?->user_id,
                        'rider_id' => $lastMileRider?->id,
                        'hub_transfer_id' => null,
                        'hub_transport_driver_id' => null,
                        'logistics_center_id' => $lastMileRider?->center_id ?? $destinationCenter?->id ?? $originCenter?->id,
                        'recipient_name' => $lastMileRider?->name ?? 'Last-Mile Delivery Rider (Pending)',
                        'rule_applied' => 'PHP '.number_format($calc['last_mile_rider_fee'], 2).' per seller shipment'
                            .($isCod ? ' (deducted directly from COD)' : ''),
                        'settlement_mode' => $isCod ? 'cod_retained' : 'escrow_bank_remittance',
                        'earned_ready' => (bool) $isDelivered,
                        'auto_remitted' => (bool) ($isDelivered && $isCod),
                    ],
                    EscrowRemittance::ROLE_ADMIN => [
                        'amount' => $calc['admin_commission'],
                        'recipient_user_id' => $adminUser?->id,
                        'rider_id' => null,
                        'hub_transfer_id' => null,
                        'hub_transport_driver_id' => null,
                        'logistics_center_id' => null,
                        'recipient_name' => 'Invoiz Platform Admin',
                        'rule_applied' => number_format($calc['commission_rate'], 2).'% commission on PHP '
                            .number_format($calc['net_merch'], 2).' net merchandise',
                        'settlement_mode' => 'platform_retained',
                        'earned_ready' => (bool) $isSettled,
                        'auto_remitted' => (bool) $isSettled,
                    ],
                    EscrowRemittance::ROLE_SELLER => [
                        'amount' => $calc['seller_earning'],
                        'recipient_user_id' => $sellerId,
                        'rider_id' => null,
                        'hub_transfer_id' => null,
                        'hub_transport_driver_id' => null,
                        'logistics_center_id' => null,
                        'recipient_name' => $sellerDisplay,
                        'rule_applied' => 'Remaining product earnings after all deductions'
                            .($calc['shipping_waived'] > 0
                                ? ' (incl. PHP '.number_format($calc['shipping_waived'], 2).' free-shipping absorbed)'
                                : '')
                            .($calc['seller_deficit'] > 0
                                ? ' (deficit PHP '.number_format($calc['seller_deficit'], 2).' covered by platform)'
                                : ''),
                        'settlement_mode' => 'seller_wallet_credit',
                        'earned_ready' => (bool) $isSettled,
                        'auto_remitted' => (bool) $isSettled,
                    ],
                ];

                foreach ($rows as $role => $meta) {
                    $existing = EscrowRemittance::where('order_id', $order->id)
                        ->where('seller_id', $sellerId)
                        ->where('recipient_role', $role)
                        ->first();

                    // Never rewrite a settled remittance; only cancellation
                    // may void it, and even then the amount is preserved.
                    if ($existing && $existing->status === 'remitted' && ! $isCancelled) {
                        continue;
                    }
                    if ($existing && $isCancelled) {
                        $existing->update(['status' => 'voided', 'notes' => 'Order cancelled or payment failed.']);

                        continue;
                    }

                    if ($isCancelled) {
                        $status = 'voided';
                    } elseif (! empty($meta['auto_remitted'])) {
                        $status = 'remitted';
                    } elseif ((float) $meta['amount'] <= 0.0) {
                        $status = $isSettled ? 'remitted' : 'held_in_escrow';
                    } elseif ($isSettled || (! empty($meta['earned_ready']) && $isDelivered)) {
                        $status = 'pending_remittance';
                    } else {
                        $status = 'held_in_escrow';
                    }

                    $remittedAt = $existing?->remitted_at;
                    $bankReference = $existing?->bank_reference;
                    if ($status === 'remitted' && ! $remittedAt) {
                        $remittedAt = now();
                        $bankReference = $bankReference ?: match ($meta['settlement_mode']) {
                            'cod_retained' => 'COD-DEDUCT-'.($delivery?->tracking_number ?? $order->id),
                            'platform_retained' => 'ESCROW-COMM-'.$order->id,
                            'seller_wallet_credit' => 'SELLER-SETTLED-'.$order->id,
                            default => 'ESCROW-'.$order->id,
                        };
                    }

                    EscrowRemittance::updateOrCreate(
                        [
                            'order_id' => $order->id,
                            'seller_id' => $sellerId,
                            'recipient_role' => $role,
                        ],
                        [
                            'delivery_id' => $delivery?->id,
                            'payment_transaction_id' => $tx?->id,
                            'hub_transfer_id' => $meta['hub_transfer_id'],
                            'hub_transport_driver_id' => $meta['hub_transport_driver_id'],
                            'recipient_user_id' => $meta['recipient_user_id'],
                            'rider_id' => $meta['rider_id'],
                            'logistics_center_id' => $meta['logistics_center_id'],
                            'recipient_name' => $meta['recipient_name'],
                            'rule_applied' => $meta['rule_applied'],
                            'amount' => round((float) $meta['amount'], 2),
                            'settlement_mode' => $meta['settlement_mode'],
                            'status' => $status,
                            'bank_reference' => $bankReference,
                            'remitted_at' => $remittedAt,
                        ]
                    );
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('EscrowRemittance sync failed: '.$e->getMessage());
        }
    }
}

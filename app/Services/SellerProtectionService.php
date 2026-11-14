<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ReturnRequest;
use App\Support\EscrowEarnings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Compatibility reader/reconciler for already funded historical agreements. */
class SellerProtectionService
{
    public static function cents($value): int
    {
        return (int) round((float) $value * 100);
    }

    public static function policy(Order $order, int $sellerId): ?array
    {
        // Retain already funded financial agreements only. New sales never enter
        // the retired subsidy workflow, including pending orders with old snapshots.
        if (! Schema::hasTable('logistics_funding_entries') || ! DB::table('logistics_funding_entries')
            ->where('reference', 'ORDER:'.$order->id.':'.$sellerId)->whereIn('status', ['reserved', 'spent'])->exists()) {
            return null;
        }
        $snapshot = $order->getAttribute('logistics_policy_snapshot');
        if (is_string($snapshot)) {
            $snapshot = json_decode($snapshot, true);
        }

        return $snapshot['sellers'][$sellerId] ?? null;
    }

    public static function releaseUnstarted(Order $order): void
    {
        if (! $order->logistics_policy_snapshot) {
            return;
        }
        if ($order->deliveries()->where(fn ($q) => $q->whereNotNull('picked_up_at')->orWhereNotNull('received_at'))->exists()) {
            return;
        }
        foreach ($order->shippingGroupIds() as $seller) {
            LogisticsFundingService::finish('ORDER:'.$order->id.':'.$seller, false);
        }
    }

    public static function reconcile(Order $order, float $additionalRefund = 0, bool $spend = false, ?ReturnRequest $pendingReturn = null): void
    {
        if (! $order->logistics_policy_snapshot) {
            return;
        }
        if ($pendingReturn?->settlement?->sellerDirectRefund()) {
            $additionalRefund = 0;
        }
        if ($pendingReturn) {
            $order->setRelation('pendingProtectedReturn', $pendingReturn);
        }
        foreach ($order->shippingGroupIds() as $seller) {
            if (! self::policy($order, (int) $seller)) {
                continue;
            }
            $calc = EscrowEarnings::calculateForSeller($order, (int) $seller, null, null, $additionalRefund);
            $key = 'ORDER:'.$order->id.':'.$seller;
            LogisticsFundingService::reserve($key, self::cents($calc['platform_protection_subsidy']), $order->id, ['seller_id' => (int) $seller]);
            if ($spend) {
                LogisticsFundingService::finish($key, true);
            }
        }
    }

    public static function refreshLedger(Order $order): void
    {
        if (! $order->logistics_policy_snapshot) {
            return;
        }
        self::reconcile($order);
        $split = Payments::splitForSellers($order);
        $sum = fn ($key) => round(array_sum(array_column($split, $key)), 2);
        Payments::transactionFor($order)?->update(['seller_earning' => $sum('earning'), 'platform_commission' => $sum('commission'),
            'logistics_fee' => $sum('logistics'), 'platform_logistics_subsidy' => $sum('platform_logistics_subsidy')]);
        $order->update(['commission_amount' => $sum('commission')]);
    }

    public static function remainingCosts(Order $order, array $policy, $cases): array
    {
        if ($order->relationLoaded('pendingProtectedReturn')) {
            $cases = $cases->push($order->getRelation('pendingProtectedReturn'))->unique('id');
        }
        $restored = [];
        $shipping = 0;
        $external = 0;
        foreach ($cases as $case) {
            if (! isset($policy['lines'][$case->order_item_id])) {
                continue;
            }
            if ($case->workflow?->stock_restored) {
                $restored[$case->order_item_id] = ($restored[$case->order_item_id] ?? 0) + (int) $case->quantity;
            }
            if ($case->settlement?->sellerDirectRefund()) {
                $external += self::cents($case->refund_amount);
            } else {
                $shipping += (int) $case->settlement?->shipping_cents;
            }
        }
        $cost = 0;
        $lineCosts = [];
        foreach ($policy['lines'] as $id => $line) {
            $lineCosts[$id] = max(0, $line['quantity'] - min($line['quantity'], $restored[$id] ?? 0)) * $line['unit_cost_cents'];
            $cost += $lineCosts[$id];
        }

        return ['cost_cents' => $cost, 'line_costs_cents' => $lineCosts, 'external_refund_cents' => $external, 'shipping_refunded_cents' => min($policy['buyer_shipping_cents'], $shipping)];
    }

    /** Amount removed from original buyer escrow for completed logistics, excluding platform-funded shares. */
    public static function originalSubsidy(Order $order, ?int $sellerId = null): float
    {
        if (! $order->logistics_policy_snapshot) {
            return 0;
        }
        $q = DB::table('logistics_funding_entries')->where('order_id', $order->id)->whereIn('status', ['reserved', 'spent'])
            ->where('reference', 'like', 'ORDER:'.$order->id.':%');
        if ($sellerId !== null) {
            $q->where('reference', 'ORDER:'.$order->id.':'.$sellerId);
        }
        $sum = 0;
        foreach ($q->get() as $entry) {
            $seller = (int) substr($entry->reference, strrpos($entry->reference, ':') + 1);
            $calc = EscrowEarnings::calculateForSeller($order, $seller);
            $sum += min((int) $entry->amount_cents, self::cents($calc['platform_protection_subsidy']));
        }

        return $sum / 100;
    }
}

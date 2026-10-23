<?php

namespace App\Support;

use App\Models\Delivery;
use App\Models\Remittance;

class Cod
{
    public static function isCod(Delivery $delivery): bool
    {
        $method = strtolower(trim((string) $delivery->payment_method));

        if (in_array($method, ['cash_on_delivery', 'cod'], true)) {
            return true;
        }

        if ((float) ($delivery->amount_to_collect ?? 0) > 0) {
            return true;
        }

        $order = $delivery->relationLoaded('order')
            ? $delivery->order
            : $delivery->order()->with('payment')->first();

        $paymentMethod = strtolower(trim((string) ($order?->payment?->method ?? '')));

        return in_array($paymentMethod, ['cash_on_delivery', 'cod'], true);
    }

    public static function expected(Delivery $delivery): ?float
    {
        if ($delivery->amount_to_collect !== null) {
            return round((float) $delivery->amount_to_collect, 2);
        }

        if (! self::isCod($delivery)) {
            return null;
        }

        $order = $delivery->relationLoaded('order')
            ? $delivery->order
            : $delivery->order()->first();

        if (! $order) {
            return null;
        }

        $sellerId = (int) ($delivery->seller_id
            ?? $order->items()->value('seller_id'));

        if (! $sellerId) {
            return null;
        }

        return $order->cashToCollectForSeller($sellerId);
    }

    public static function received(Delivery $delivery): ?float
    {
        return $delivery->cod_amount_received === null
            ? null
            : round((float) $delivery->cod_amount_received, 2);
    }

    public static function riderFee(Delivery $delivery): float
    {
        if (! self::isCod($delivery)) {
            return 0.0;
        }

        $transaction = $delivery->relationLoaded('transaction')
            ? $delivery->transaction
            : $delivery->transaction()->first();

        $fee = $transaction?->rider_fee ?? $delivery->delivery_fee;

        return round(max(0, (float) ($fee ?? 0)), 2);
    }

    public static function reconcile(Remittance $remittance): array
    {
        $delivery = $remittance->relationLoaded('delivery')
            ? $remittance->delivery
            : $remittance->delivery()->with('transaction')->first();

        if (! $delivery) {
            return [
                'expected' => null,
                'received' => null,
                'rider_fee' => 0.0,
                'seller_amount' => round((float) $remittance->amount, 2),
                'variance' => null,
                'state' => 'unavailable',
            ];
        }

        $expected = self::expected($delivery);
        $received = self::received($delivery);
        $riderFee = self::riderFee($delivery);
        $sellerAmount = round((float) $remittance->amount, 2);
        $variance = $received === null
            ? null
            : round($received - $sellerAmount - $riderFee, 2);

        if (! self::isCod($delivery)) {
            $state = 'not_cod';
        } elseif ($received === null) {
            $state = 'unrecorded';
        } elseif (abs($variance) > 0.009) {
            $state = 'variance';
        } else {
            $state = 'balanced';
        }

        return [
            'expected' => $expected,
            'received' => $received,
            'rider_fee' => $riderFee,
            'seller_amount' => $sellerAmount,
            'variance' => $variance,
            'state' => $state,
        ];
    }
}

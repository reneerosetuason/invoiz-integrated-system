<?php

namespace App\Support;

use App\Models\Delivery;

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
}

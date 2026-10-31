<?php

namespace App\Support;

use App\Models\Delivery;
use Illuminate\Support\Facades\DB;

/**
 * Single source of data for the INVOIZ shipping waybill so the seller
 * "Print Waybill" page and the logistics parcel label render identically.
 * Read-only: never writes to orders, payments or deliveries.
 */
class Waybill
{
    /** @return array{waybillOrder:mixed,waybillAddress:mixed,waybillPayment:mixed,waybillDiscount:float,waybillStores:\Illuminate\Support\Collection} */
    public static function context(Delivery $delivery): array
    {
        $delivery->loadMissing([
            'rider', 'items', 'logisticsCenter', 'destinationCenter', 'originCenter',
            'serviceArea', 'order.items.seller',
        ]);

        $order = $delivery->order;
        $address = null;
        $payment = null;
        $discount = 0.0;
        $stores = collect();

        if ($order) {
            $snapshot = $order->shipping_address;
            $address = is_array($snapshot) && $snapshot !== []
                ? (object) $snapshot
                : DB::table('addresses')->where('id', $order->address_id)->first();
            $payment = DB::table('payments')->where('order_id', $order->id)->first();
            $discount = (float) DB::table('order_vouchers')->where('order_id', $order->id)->sum('discount_amount');

            $sellerIds = $order->items->pluck('seller_id')->filter()->unique()->values();
            if ($sellerIds->isNotEmpty()) {
                $stores = DB::table('sellers')->whereIn('user_id', $sellerIds)->get()->keyBy('user_id');
            }
        }

        return [
            'waybillOrder' => $order,
            'waybillAddress' => $address,
            'waybillPayment' => $payment,
            'waybillDiscount' => $discount,
            'waybillStores' => $stores,
        ];
    }
}


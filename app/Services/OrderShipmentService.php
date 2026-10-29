<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\PickupRequest;
use App\Models\Seller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderShipmentService
{
    /** Build the rider's shipment from the order's immutable item/address snapshots. */
    public function ensure(Order $order, bool $notify = false): Delivery
    {
        return DB::transaction(function () use ($order, $notify) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $order->load(['items', 'address', 'buyer', 'payment', 'orderVouchers.voucher']);
            $groups = $order->items->pluck('seller_id')->map(fn ($id) => (int) $id)->unique()->values();
            if ($groups->count() !== 1) {
                throw ValidationException::withMessages(['order' => 'Each shipment must belong to exactly one store.']);
            }
            $sellerId = $groups->first();
            $store = Seller::withTrashed()->with('user')->where('user_id', $sellerId)->first();
            $address = $order->shipping_address;
            if (! is_array($address) || ! $address) {
                $address = (int) $order->address?->buyer_id === (int) $order->buyer_id
                    ? $order->address->toShippingSnapshot() : [];
            }
            $recipientAddress = implode(', ', array_filter(array_map(
                fn ($key) => trim((string) ($address[$key] ?? '')),
                ['address_line', 'barangay', 'city', 'province', 'region', 'postal_code']
            )));
            $delivery = Delivery::where('order_id', $order->id)->lockForUpdate()->first();
            if ($delivery && (int) $delivery->seller_id !== $sellerId) {
                throw ValidationException::withMessages(['order' => 'Shipment ownership does not match the order. Please ask logistics to review it.']);
            }
            $new = ! $delivery;
            $delivery ??= new Delivery([
                'order_id' => $order->id, 'seller_id' => $sellerId,
                'status' => 'waiting_for_rider', 'parcel_status' => 'pending_arrival',
                'payment_method' => $order->payment?->method === 'cod' ? 'cash_on_delivery' : ($order->payment?->method ?? 'cash_on_delivery'),
                'amount_to_collect' => $order->cashToCollectForSeller($sellerId),
                'delivery_fee' => Order::SHIPPING_FEE_PER_SELLER,
            ]);
            // Never replace a courier's existing snapshot during a repair.
            foreach ([
                'sender_name' => $store?->business_name ?: $store?->user?->name,
                'sender_phone' => $store?->user?->phone,
                'sender_address' => $store?->fullAddress(),
                'recipient_name' => $address['recipient_name'] ?? $order->buyer?->name,
                'recipient_phone' => $address['phone'] ?? null,
                'recipient_address' => $recipientAddress,
                'notes' => $order->notes,
            ] as $key => $value) {
                if (blank($delivery->$key) && filled($value)) {
                    $delivery->$key = $value;
                }
            }
            $delivery->save();
            if (! $delivery->items()->exists()) {
                foreach ($order->items as $item) {
                    $delivery->items()->create([
                        'name' => $item->product_name, 'variant_label' => $item->variant_label,
                        'quantity' => $item->quantity, 'price' => $item->unit_price ?? $item->price,
                    ]);
                }
            }
            if (! in_array($order->status, ['cancelled', 'delivered'], true) && ! $delivery->picked_up_at) {
                PickupRequest::openForDelivery($delivery, $notify && $order->status === 'ready_for_delivery');
            }
            return $delivery;
        }, 3);
    }
}

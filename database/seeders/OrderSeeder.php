<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $sellers = Seller::where('status', 'approved')->get();
        $buyers = User::where('role', 'buyer')->get();

        if ($sellers->isEmpty() || $buyers->isEmpty()) {
            return;
        }

        $statuses = ['new', 'processing', 'shipped', 'delivered', 'cancelled'];
        $paymentStatuses = ['pending', 'paid', 'failed', 'refunded'];

        for ($i = 0; $i < 35; $i++) {
            $seller = $sellers->random();
            $buyer = $buyers->random();

            // Pick a product that actually belongs to this seller (e.g. GlamEssence orders get Makeup/Jewelry)
            $product = \App\Models\Product::where('seller_id', $seller->id)->inRandomOrder()->first();
            if (!$product) {
                $product = \App\Models\Product::inRandomOrder()->first();
            }
            $quantity = rand(1, 3);
            $unitPrice = (float) $product->price;
            $subTotal = $quantity * $unitPrice;
            $shippingFee = 49.00;
            $taxTotal = round($subTotal * 0.12, 2);
            $total = round($subTotal + $shippingFee + $taxTotal, 2);

            $date = now()->subDays(rand(0, 365))->setTime(rand(9, 18), rand(0, 59), 0);
            $order = Order::create([
                'order_number' => 'ORD-' . str_pad($i + 1, 5, '0', STR_PAD_LEFT),
                'seller_id' => $seller->id,
                'buyer_id' => $buyer->id,
                'status' => $statuses[array_rand($statuses)],
                'payment_status' => $paymentStatuses[array_rand($paymentStatuses)],
                'sub_total' => $subTotal,
                'shipping_fee' => $shippingFee,
                'tax_total' => $taxTotal,
                'total' => $total,
                'commission_amount' => round($subTotal * 0.10, 2),
                'delivery_status' => 'pending',
                'shipping_address' => ['street' => '123 Main St', 'city' => 'Manila', 'zip' => '1000'],
                'created_at' => $date,
                'updated_at' => $date,
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $subTotal,
            ]);
        }
    }
}

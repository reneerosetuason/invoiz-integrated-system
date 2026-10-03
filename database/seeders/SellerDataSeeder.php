<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\Message;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Voucher;
use Illuminate\Database\Seeder;

class SellerDataSeeder extends Seeder
{
    public function run(): void
    {
        $sellers = Seller::where('status', 'approved')->get();
        $buyers = \App\Models\User::where('role', 'buyer')->get();
        $orders = \App\Models\Order::all();

        // 1) Default variant + inventory per product
        foreach (Product::all() as $product) {
            $variant = ProductVariant::firstOrCreate(
                ['product_id' => $product->id, 'sku' => ($product->sku ?? 'SKU') . '-D'],
                [
                    'attributes' => ['Default'],
                    'price' => $product->price,
                    'stock' => rand(12, 60),
                    'weight' => 0.5,
                ]
            );

            Inventory::firstOrCreate(
                ['seller_id' => $product->seller_id, 'product_variant_id' => $variant->id],
                ['quantity' => $variant->stock, 'reserved' => 0]
            );
        }

        // 2) Vouchers per seller
        $voucherDefs = [
            ['INVOIZ10', 'fixed', 100.00, 500],
            ['INVOIZ5', 'percent', 5.00, 0],
            ['SHIPFREE', 'fixed', 49.00, 200],
            ['TECH5', 'percent', 5.00, 300],
            ['FRESH10', 'fixed', 80.00, 400],
        ];

        foreach ($sellers as $i => $seller) {
            $def = $voucherDefs[$i % count($voucherDefs)];
            Voucher::firstOrCreate(
                ['code' => $def[0] . ($i === 0 ? '' : $i)],
                [
                    'seller_id' => $seller->id,
                    'type' => $def[1],
                    'value' => $def[2],
                    'min_spend' => $def[3],
                    'starts_at' => now()->subDays(10),
                    'ends_at' => now()->addDays(60),
                    'usage_limit' => 200,
                    'used_count' => rand(4, 30),
                    'status' => true,
                ]
            );
        }

        // 3) Product reviews
        if ($buyers->isEmpty()) {
            return;
        }

        $products = Product::all();
        $reviews = [
            [5, 'Very good quality, exactly as described. My baby loves it!'],
            [4, 'Good product overall. Delivery was a bit slow but worth it.'],
            [5, 'Highly recommended! Great value for the price.'],
            [3, 'Decent item but packaging could be improved.'],
            [5, 'Fast shipping and excellent quality. Will order again.'],
            [4, 'Nice product, matches the photos perfectly.'],
            [5, 'Amazing! My kids are very happy with this purchase.'],
        ];

        for ($i = 0; $i < 12; $i++) {
            $product = $products->random();
            $buyer = $buyers->random();
            $order = $orders->filter(fn ($o) => $o->seller_id === $product->seller_id)->first();

            [$rating, $review] = $reviews[$i % count($reviews)];

            ProductReview::firstOrCreate(
                ['product_id' => $product->id, 'buyer_id' => $buyer->id],
                [
                    'order_id' => $order?->id,
                    'rating' => $rating,
                    'review' => $review,
                    'status' => 'approved',
                ]
            );
        }

        // 4) Sample buyer conversations
        $conversations = [
            ['Hi! Is this still available?', 'Yes, it is. Ready to ship within 24 hours.'],
            ['Do you have size variations for this item?', 'We currently have the default size in stock.'],
            ['How long is the delivery?', 'Metro Manila usually arrives in 2–3 days.'],
            ['Can I request a discount voucher?', 'Sure! Check our voucher section, there is a storewide promo right now.'],
            ['The item arrived today, thank you!', 'Thank you for your purchase! Please rate the product when you have time.'],
        ];

        foreach ($conversations as $i => [$buyerMsg, $sellerMsg]) {
            $sellerUser = $sellers[$i % $sellers->count()]->user;
            $buyer = $buyers[$i % $buyers->count()];

            Message::firstOrCreate(
                ['sender_id' => $buyer->id, 'receiver_id' => $sellerUser->id, 'body' => $buyerMsg],
                ['is_read' => $i % 2 === 0]
            );
            Message::firstOrCreate(
                ['sender_id' => $sellerUser->id, 'receiver_id' => $buyer->id, 'body' => $sellerMsg],
                ['is_read' => true]
            );
        }
    }
}
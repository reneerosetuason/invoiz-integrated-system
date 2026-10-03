<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Seller;
use App\Models\Category;
use App\Models\CommissionRate;
use App\Models\PaymentTransaction;
use App\Models\SellerPayout;
use App\Models\WithdrawalRequest;
use App\Models\PaymentDispute;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        // ------------------------------------------------------------------
        // Commission rates per category (Electronics, Fashion, Beauty, etc.)
        // ------------------------------------------------------------------
        $rates = [
            'Electronics & Gadgets' => 8.00,
            'Gadgets' => 8.00,
            'Dresses' => 12.00,
            "Women's Apparel" => 12.00,
            'Makeup' => 10.00,
            'Jewelry' => 6.00,
        ];

        foreach ($rates as $name => $rate) {
            $categoryId = Category::where('name', $name)->value('id');
            if ($categoryId) {
                CommissionRate::updateOrCreate(
                    ['category_id' => $categoryId],
                    ['rate' => $rate]
                );
            }
        }

        // Default rate (applies to categories without a specific rate)
        CommissionRate::updateOrCreate(
            ['category_id' => null],
            ['rate' => 10.00]
        );

        // ------------------------------------------------------------------
        // Payment transactions derived from orders (full money flow)
        // ------------------------------------------------------------------
        $gateways = ['GCash', 'Maya', 'Credit Card', 'COD'];
        $refSeq = 1;

        foreach (Order::orderBy('id')->get() as $order) {
            $status = match ($order->payment_status) {
                'paid' => 'successful',
                'pending' => 'pending',
                'failed' => 'failed',
                'refunded' => 'refunded',
                default => 'pending',
            };

            // A few refunded orders become partially refunded
            if ($status === 'refunded' && ($refSeq % 3 === 0)) {
                $status = 'partially_refunded';
            }

            $amount = (float) $order->total;
            $commission = $status === 'successful' ? (float) $order->commission_amount : 0.0;
            $riderFee = $status === 'successful' ? 45.00 : 0.0;
            $sellerEarning = $status === 'successful' ? max(0, $amount - $commission - $riderFee) : 0.0;
            $refunded = 0.0;

            if ($status === 'refunded') {
                $refunded = $amount;
                $commission = 0.0;
                $sellerEarning = 0.0;
            } elseif ($status === 'partially_refunded') {
                $refunded = round($amount * 0.3, 2);
                $commission = round($commission * 0.7, 2);
                $sellerEarning = round(max(0, $amount - $refunded - $commission - $riderFee), 2);
            }

            PaymentTransaction::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'transaction_ref' => 'TXN-' . str_pad($refSeq, 5, '0', STR_PAD_LEFT),
                    'gateway' => $gateways[array_rand($gateways)],
                    'amount' => $amount,
                    'status' => $status,
                    'seller_earning' => $sellerEarning,
                    'platform_commission' => $commission,
                    'rider_fee' => $riderFee,
                    'refunded_amount' => $refunded,
                    'paid_at' => in_array($status, ['successful', 'refunded', 'partially_refunded'])
                        ? $order->created_at
                        : null,
                ]
            );

            $refSeq++;
        }

        // ------------------------------------------------------------------
        // Seller payouts (paid + pending per seller)
        // ------------------------------------------------------------------
        foreach (Seller::where('status', 'approved')->get() as $i => $seller) {
            SellerPayout::create([
                'seller_id' => $seller->id,
                'amount' => rand(200000, 800000) / 100,
                'status' => 'paid',
                'method' => 'Bank Transfer',
                'reference' => 'PAYOUT-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'paid_at' => now()->subDays(rand(5, 20)),
            ]);

            SellerPayout::create([
                'seller_id' => $seller->id,
                'amount' => rand(150000, 600000) / 100,
                'status' => 'pending',
                'method' => 'GCash',
            ]);
        }

        // ------------------------------------------------------------------
        // Withdrawal requests
        // ------------------------------------------------------------------
        $withdrawals = [
            ['status' => 'pending', 'method' => 'GCash'],
            ['status' => 'pending', 'method' => 'Bank Transfer'],
            ['status' => 'approved', 'method' => 'Bank Transfer'],
        ];

        $payoutSellers = Seller::where('status', 'approved')->take(3)->get();
        foreach ($payoutSellers as $i => $seller) {
            WithdrawalRequest::create([
                'seller_id' => $seller->id,
                'amount' => rand(100000, 450000) / 100,
                'status' => $withdrawals[$i]['status'],
                'method' => $withdrawals[$i]['method'],
                'processed_at' => $withdrawals[$i]['status'] === 'approved' ? now()->subDays(2) : null,
            ]);
        }

        // ------------------------------------------------------------------
        // Payment disputes
        // ------------------------------------------------------------------
        $disputedOrders = Order::whereIn('payment_status', ['paid', 'refunded'])->take(2)->get();

        if ($disputedOrders->count() > 0) {
            $order = $disputedOrders[0];
            PaymentDispute::create([
                'order_id' => $order->id,
                'buyer_id' => $order->buyer_id,
                'seller_id' => $order->seller_id,
                'amount' => $order->total,
                'reason' => 'Item not received as described',
                'status' => 'open',
            ]);
        }

        if ($disputedOrders->count() > 1) {
            $order = $disputedOrders[1];
            PaymentDispute::create([
                'order_id' => $order->id,
                'buyer_id' => $order->buyer_id,
                'seller_id' => $order->seller_id,
                'amount' => $order->total,
                'reason' => 'Duplicate charge on payment',
                'status' => 'resolved',
                'resolution_note' => 'Refund issued to buyer after verification.',
                'resolved_at' => now()->subDays(1),
            ]);
        }
    }
}

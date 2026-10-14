<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Connects existing data into the payment ledger:
 * - paid payments  -> orders.payment_status = paid + settled split row
 * - other orders   -> pending/failed ledger rows so every order is covered
 * - delivered rows missing paid flags get both
 * Safe to re-run: every step skips work that already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasTable('payment_transactions')) {
            return;
        }

        \App\Models\Order::with(['items', 'orderVouchers.voucher', 'payment'])
            ->chunkById(200, function ($orders) {
                foreach ($orders as $order) {
                    try {
                        $payStatus = $order->payment ? $order->payment->status : null;

                        if ($payStatus === 'paid' || $order->status === 'delivered') {
                            if ($order->payment && $order->payment->status === 'pending') {
                                $order->payment->update(['status' => 'paid', 'paid_at' => $order->updated_at ?? now()]);
                            }
                            if ($order->payment_status !== 'paid') {
                                $order->update(['payment_status' => 'paid']);
                            }
                            \App\Support\Payments::settleForDelivery($order);
                        } elseif ($order->status === 'cancelled') {
                            \App\Support\Payments::voidForCancel($order);
                        } else {
                            \App\Support\Payments::recordCheckout($order);
                        }
                    } catch (\Throwable $e) {
                        \Log::warning("Payment backfill skipped order #{$order->id}: " . $e->getMessage());
                    }
                }
            });
    }

    public function down(): void
    {
        // Ledger history is informational; nothing to undo.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The logistics-fee column belongs on BOTH ledgers:
 * - payment_transactions.logistics_fee (marketplace split)
 * - transactions.logistics_fee (per-delivery ops record)
 * Then recompute settled rows onto the final formula.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['payment_transactions', 'transactions'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'logistics_fee')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->decimal('logistics_fee', 12, 2)->default(0)->after('rider_fee');
                });
            }
        }

        try {
            \App\Models\PaymentTransaction::where('status', 'successful')
                ->chunkById(200, function ($txs) {
                    foreach ($txs as $tx) {
                        try {
                            $order = \App\Models\Order::with(['items', 'orderVouchers.voucher'])->find($tx->order_id);
                            if ($order) {
                                \App\Support\Payments::settleForDelivery($order, true);
                            }
                        } catch (\Throwable $e) {
                            \Log::warning("Logistics-fee backfill skipped tx #{$tx->id}: " . $e->getMessage());
                        }
                    }
                });
        } catch (\Throwable $e) {
            \Log::warning('Logistics-fee backfill failed: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // Ledger history is informational; nothing to undo.
    }
};

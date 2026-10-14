<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1) transactions.logistics_fee: the flat ₱15 per-delivery operations fee,
 *    funded out of the seller's share (admin keeps exactly 10%).
 * 2) deliveries.seller_id: which seller group a delivery belongs to, so a
 *    rider->seller cash handoff is always attributable.
 * 3) remittances: off-system COD cash flow rider -> seller. A sale only
 *    settles (payment paid, ledger booked) after the SELLER confirms the
 *    cash actually arrived.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('transactions', 'logistics_fee')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->decimal('logistics_fee', 12, 2)->default(0)->after('rider_fee');
            });
        }

        if (! Schema::hasColumn('deliveries', 'seller_id')) {
            Schema::table('deliveries', function (Blueprint $table) {
                $table->unsignedBigInteger('seller_id')->nullable()->after('order_id');
                $table->index('seller_id');
            });
        }

        if (! Schema::hasTable('remittances')) {
            Schema::create('remittances', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->index();
                $table->unsignedBigInteger('delivery_id')->nullable()->unique();
                $table->unsignedBigInteger('rider_id')->nullable()->index();
                $table->unsignedBigInteger('seller_id')->index();
                $table->decimal('amount', 12, 2)->default(0);
                $table->enum('status', ['pending', 'sent', 'confirmed'])->default('pending');
                $table->string('reference', 150)->nullable();
                $table->text('note')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamps();
            });
        }

        // Backfill already-settled history so every delivered order has a
        // matching confirmed handoff (money presumed moved with settlement).
        try {
            \App\Models\Order::with(['items', 'orderVouchers.voucher', 'deliveries'])
                ->where('status', 'delivered')
                ->chunkById(200, function ($orders) {
                    foreach ($orders as $order) {
                        try {
                            // Tag existing delivery rows with their seller group.
                            foreach ($order->deliveries as $delivery) {
                                if (! $delivery->seller_id) {
                                    $firstSeller = $order->items->pluck('seller_id')->filter()->first();
                                    if ($firstSeller) {
                                        $delivery->update(['seller_id' => (int) $firstSeller]);
                                    }
                                }
                                if (! \App\Models\Remittance::where('delivery_id', $delivery->id)->exists()) {
                                    \App\Support\Remittances::confirmForBackfill($delivery);
                                }
                            }
                            if ($order->deliveries->isEmpty()) {
                                \App\Support\Remittances::confirmForBackfill(null, $order);
                            }
                            // Bring old settled splits onto the new formula.
                            \App\Support\Payments::settleForDelivery($order);
                            $tx = \App\Support\Payments::transactionFor($order);
                            if ($tx && $tx->status === 'successful') {
                                $groups = $order->shippingGroupIds();
                                $tx->update(['logistics_fee' => round(count($groups) * 15.0, 2)]);
                            }
                        } catch (\Throwable $e) {
                            \Log::warning("Remittance backfill skipped order #{$order->id}: " . $e->getMessage());
                        }
                    }
                });
        } catch (\Throwable $e) {
            \Log::warning('Remittance backfill failed: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('remittances');
        if (Schema::hasColumn('deliveries', 'seller_id')) {
            Schema::table('deliveries', function (Blueprint $table) {
                $table->dropIndex(['seller_id']);
                $table->dropColumn('seller_id');
            });
        }
        if (Schema::hasColumn('transactions', 'logistics_fee')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('logistics_fee');
            });
        }
    }
};

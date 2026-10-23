<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columns/tables the merged logistics code expects but the marketplace
 * migrations never created:
 *  - deliveries.assigned_at / picked_up_at: the rider accept + pickup
 *    transitions write these and every delivery view reads them.
 *  - store_follows: buyer "follow store" (Api\StoreController, product
 *    follower counts).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            if (! Schema::hasColumn('deliveries', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('estimated_delivery_at');
            }
            if (! Schema::hasColumn('deliveries', 'picked_up_at')) {
                $table->timestamp('picked_up_at')->nullable()->after('accepted_at');
            }
        });

        if (! Schema::hasTable('store_follows')) {
            Schema::create('store_follows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['buyer_id', 'seller_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('store_follows');

        Schema::table('deliveries', function (Blueprint $table) {
            foreach (['assigned_at', 'picked_up_at'] as $column) {
                if (Schema::hasColumn('deliveries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

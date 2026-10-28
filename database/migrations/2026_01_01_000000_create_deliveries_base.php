<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base deliveries table for shared DBs (e.g. invoiz_ecommerce_db).
 *
 * No migration in this project creates `deliveries` — it was built
 * outside migrations in the original logistics database. Later logistics
 * migrations only ALTER it (order_id change, drop fk_deliveries_rider,
 * columns positioned after delivery_notes/delivered_at/priority), so this
 * creates the minimal anchor schema those alters expect. pickup_pin,
 * failure_reason, package_type/package_description/priority are
 * intentionally EXCLUDED — later migrations add them.
 * Create-only-if-missing; never touches data.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('deliveries')) {
            return;
        }

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('rider_id')->nullable();
            $table->string('status', 50)->default('waiting_for_rider');
            $table->text('delivery_notes')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->foreign('rider_id', 'fk_deliveries_rider')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Non-destructive: never drop shared data on rollback.
    }
};

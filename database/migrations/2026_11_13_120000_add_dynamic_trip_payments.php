<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $t) {
            foreach (['length_cm', 'width_cm', 'height_cm'] as $field) {
                $t->decimal($field, 10, 2)->nullable();
            }
        });
        Schema::table('logistics_trucks', function (Blueprint $t) {
            foreach (['cargo_length_cm', 'cargo_width_cm', 'cargo_height_cm'] as $field) {
                $t->decimal($field, 10, 2)->nullable();
            }
        });
        Schema::table('hub_transfers', function (Blueprint $t) {
            // Null marks legacy manifests; never backfill historical compensation.
            $t->unsignedTinyInteger('payment_version')->nullable();
            $t->decimal('route_distance_km', 10, 3)->nullable();
            $t->string('route_distance_source')->nullable();
            $t->unsignedBigInteger('route_recorded_by')->nullable();
            $t->json('payment_snapshot')->nullable();
            $t->timestamp('payment_confirmed_at')->nullable();
            $t->unsignedBigInteger('payment_confirmed_by')->nullable();
        });
        Schema::table('hub_transfer_parcels', function (Blueprint $t) {
            $t->unsignedBigInteger('driver_allocation_cents')->nullable();
        });
        Schema::table('escrow_remittances', function (Blueprint $t) {
            $t->string('settlement_reference')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('escrow_remittances', fn (Blueprint $t) => $t->dropColumn('settlement_reference'));
        Schema::table('hub_transfer_parcels', fn (Blueprint $t) => $t->dropColumn('driver_allocation_cents'));
        Schema::table('hub_transfers', fn (Blueprint $t) => $t->dropColumn(['payment_version', 'route_distance_km', 'route_distance_source', 'route_recorded_by', 'payment_snapshot', 'payment_confirmed_at', 'payment_confirmed_by']));
        Schema::table('logistics_trucks', fn (Blueprint $t) => $t->dropColumn(['cargo_length_cm', 'cargo_width_cm', 'cargo_height_cm']));
        Schema::table('deliveries', fn (Blueprint $t) => $t->dropColumn(['length_cm', 'width_cm', 'height_cm']));
    }
};

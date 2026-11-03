<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Allow vehicle_type to be nullable on rider_applications so Hub Transport Drivers do not need to submit vehicle details
        Schema::table('rider_applications', function (Blueprint $table) {
            $table->string('vehicle_type', 255)->nullable()->change();
        });

        // 2. Add user_id to hub_transport_drivers if not present
        if (! Schema::hasColumn('hub_transport_drivers', 'user_id')) {
            Schema::table('hub_transport_drivers', function (Blueprint $table) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('logistics_company_id')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        // 3. Add additive four-wheel vehicle types to vehicle_types
        if (Schema::hasTable('vehicle_types')) {
            DB::table('vehicle_types')->updateOrInsert(
                ['name' => 'l300'],
                [
                    'label' => 'L300 / Utility Van',
                    'capacity_kg' => 1000.00,
                    'is_active' => true,
                    'sort_order' => 5,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            DB::table('vehicle_types')->updateOrInsert(
                ['name' => 'light_truck'],
                [
                    'label' => 'Light Truck (4-Wheel)',
                    'capacity_kg' => 1500.00,
                    'is_active' => true,
                    'sort_order' => 6,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('hub_transport_drivers', 'user_id')) {
            Schema::table('hub_transport_drivers', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('rider_applications')) {
            Schema::table('rider_applications', function (Blueprint $table): void {
                if (! Schema::hasColumn('rider_applications', 'vehicle_name')) {
                    $table->string('vehicle_name', 255)->nullable()->after('vehicle_type');
                }
                if (! Schema::hasColumn('rider_applications', 'vehicle_model')) {
                    $table->string('vehicle_model', 255)->nullable()->after('vehicle_name');
                }
                if (! Schema::hasColumn('rider_applications', 'parcel_capacity')) {
                    $table->unsignedInteger('parcel_capacity')->nullable()->after('vehicle_model');
                }
                if (! Schema::hasColumn('rider_applications', 'capacity_kg')) {
                    $table->decimal('capacity_kg', 10, 2)->nullable()->after('parcel_capacity');
                }
                if (! Schema::hasColumn('rider_applications', 'logistics_truck_id')) {
                    $table->foreignId('logistics_truck_id')->nullable()->after('service_area_id')->constrained('logistics_trucks')->nullOnDelete();
                }
                if (! Schema::hasColumn('rider_applications', 'hub_transport_driver_id')) {
                    $table->foreignId('hub_transport_driver_id')->nullable()->after('logistics_truck_id')->constrained('hub_transport_drivers')->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('rider_applications')) {
            Schema::table('rider_applications', function (Blueprint $table): void {
                if (Schema::hasColumn('rider_applications', 'hub_transport_driver_id')) {
                    $table->dropConstrainedForeignId('hub_transport_driver_id');
                }
                if (Schema::hasColumn('rider_applications', 'logistics_truck_id')) {
                    $table->dropConstrainedForeignId('logistics_truck_id');
                }
                $drops = [];
                foreach (['capacity_kg', 'parcel_capacity', 'vehicle_model', 'vehicle_name'] as $col) {
                    if (Schema::hasColumn('rider_applications', $col)) {
                        $drops[] = $col;
                    }
                }
                if (! empty($drops)) {
                    $table->dropColumn($drops);
                }
            });
        }
    }
};

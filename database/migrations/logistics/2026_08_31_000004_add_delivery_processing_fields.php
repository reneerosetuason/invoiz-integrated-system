<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            if (! Schema::hasColumn('deliveries', 'center_id')) {
                $table->foreignId('center_id')->nullable()->after('rider_id')->constrained('logistics_centers')->nullOnDelete();
            }
            if (! Schema::hasColumn('deliveries', 'destination_center_id')) {
                $table->foreignId('destination_center_id')->nullable()->after('center_id')->constrained('logistics_centers')->nullOnDelete();
            }
            if (! Schema::hasColumn('deliveries', 'service_area_id')) {
                $table->foreignId('service_area_id')->nullable()->after('destination_center_id')->constrained('service_areas')->nullOnDelete();
            }
            if (! Schema::hasColumn('deliveries', 'parcel_status')) {
                $table->enum('parcel_status', [
                    'pending_arrival', 'received', 'scanned', 'sorted', 'dispatched'
                ])->default('pending_arrival')->after('priority');
            }
            if (! Schema::hasColumn('deliveries', 'received_at')) {
                $table->timestamp('received_at')->nullable()->after('parcel_status');
            }
            if (! Schema::hasColumn('deliveries', 'scanned_at')) {
                $table->timestamp('scanned_at')->nullable()->after('received_at');
            }
            if (! Schema::hasColumn('deliveries', 'sorted_at')) {
                $table->timestamp('sorted_at')->nullable()->after('scanned_at');
            }
        });

        // Widen-only: shared DBs already carry logistics_admin/logistics_staff
        // roles — never narrow the enum here.
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'seller', 'buyer', 'rider', 'staff', 'logistics_admin', 'logistics_staff'])
                  ->default('buyer')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'seller', 'buyer', 'rider'])
                  ->default('buyer')->change();
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropForeign(['center_id', 'destination_center_id', 'service_area_id']);
            $table->dropColumn([
                'center_id', 'destination_center_id', 'service_area_id',
                'parcel_status', 'received_at', 'scanned_at', 'sorted_at',
            ]);
        });
    }
};

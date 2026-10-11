<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->foreignId('center_id')->nullable()->after('rider_id')->constrained('logistics_centers')->nullOnDelete();
            $table->foreignId('destination_center_id')->nullable()->after('center_id')->constrained('logistics_centers')->nullOnDelete();
            $table->foreignId('service_area_id')->nullable()->after('destination_center_id')->constrained('service_areas')->nullOnDelete();
            $table->enum('parcel_status', [
                'pending_arrival', 'received', 'scanned', 'sorted', 'dispatched'
            ])->default('pending_arrival')->after('priority');
            $table->timestamp('received_at')->nullable()->after('parcel_status');
            $table->timestamp('scanned_at')->nullable()->after('received_at');
            $table->timestamp('sorted_at')->nullable()->after('scanned_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'seller', 'buyer', 'rider', 'staff'])
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

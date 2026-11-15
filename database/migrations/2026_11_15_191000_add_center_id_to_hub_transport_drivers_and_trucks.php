<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('hub_transport_drivers') && !Schema::hasColumn('hub_transport_drivers', 'center_id')) {
            Schema::table('hub_transport_drivers', function (Blueprint $table) {
                $table->foreignId('center_id')->nullable()->after('logistics_company_id')->constrained('logistics_centers')->nullOnDelete();
            });
        }

        if (Schema::hasTable('logistics_trucks') && !Schema::hasColumn('logistics_trucks', 'center_id')) {
            Schema::table('logistics_trucks', function (Blueprint $table) {
                $table->foreignId('center_id')->nullable()->after('logistics_company_id')->constrained('logistics_centers')->nullOnDelete();
            });
        }

        // Backfill center_id from associated users
        if (Schema::hasColumn('hub_transport_drivers', 'center_id')) {
            DB::statement('UPDATE hub_transport_drivers d JOIN users u ON d.user_id = u.id SET d.center_id = u.center_id WHERE u.center_id IS NOT NULL');
        }

        // Backfill center_id for logistics_trucks from paired drivers
        if (Schema::hasColumn('logistics_trucks', 'center_id') && Schema::hasColumn('hub_transport_drivers', 'center_id')) {
            DB::statement('UPDATE logistics_trucks t JOIN hub_transport_drivers d ON d.logistics_truck_id = t.id SET t.center_id = d.center_id WHERE d.center_id IS NOT NULL');
            DB::statement('UPDATE logistics_trucks t JOIN hub_transport_drivers d ON t.hub_transport_driver_id = d.id SET t.center_id = d.center_id WHERE d.center_id IS NOT NULL AND t.center_id IS NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('logistics_trucks') && Schema::hasColumn('logistics_trucks', 'center_id')) {
            Schema::table('logistics_trucks', function (Blueprint $table) {
                $table->dropConstrainedForeignId('center_id');
            });
        }

        if (Schema::hasTable('hub_transport_drivers') && Schema::hasColumn('hub_transport_drivers', 'center_id')) {
            Schema::table('hub_transport_drivers', function (Blueprint $table) {
                $table->dropConstrainedForeignId('center_id');
            });
        }
    }
};

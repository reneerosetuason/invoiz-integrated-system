<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('logistics_companies')) {
            return;
        }

        // Ensure primary company 1 exists
        $invoiz = DB::table('logistics_companies')->where('id', 1)->first();
        if ($invoiz) {
            DB::table('logistics_companies')->where('id', 1)->update([
                'name' => 'INVOIZ Logistics',
                'status' => 'active',
                'deleted_at' => null,
                'updated_at' => now(),
            ]);
        }

        // Consolidate centers
        if (Schema::hasTable('logistics_centers') && Schema::hasColumn('logistics_centers', 'logistics_company_id')) {
            DB::table('logistics_centers')
                ->whereNull('logistics_company_id')
                ->orWhere('logistics_company_id', '!=', 1)
                ->update(['logistics_company_id' => 1]);
        }

        // Consolidate logistics staff & riders
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'logistics_company_id')) {
            DB::table('users')
                ->whereNotNull('logistics_company_id')
                ->where('logistics_company_id', '!=', 1)
                ->update(['logistics_company_id' => 1]);
        }

        // Consolidate trucks
        if (Schema::hasTable('logistics_trucks') && Schema::hasColumn('logistics_trucks', 'logistics_company_id')) {
            DB::table('logistics_trucks')
                ->where('logistics_company_id', '!=', 1)
                ->update(['logistics_company_id' => 1]);
        }

        // Consolidate hub transport drivers
        if (Schema::hasTable('hub_transport_drivers') && Schema::hasColumn('hub_transport_drivers', 'logistics_company_id')) {
            DB::table('hub_transport_drivers')
                ->where('logistics_company_id', '!=', 1)
                ->update(['logistics_company_id' => 1]);
        }

        // Consolidate hub transfers
        if (Schema::hasTable('hub_transfers') && Schema::hasColumn('hub_transfers', 'logistics_company_id')) {
            DB::table('hub_transfers')
                ->where('logistics_company_id', '!=', 1)
                ->update(['logistics_company_id' => 1]);
        }

        // Consolidate hub transfer earnings
        if (Schema::hasTable('hub_transfer_earnings') && Schema::hasColumn('hub_transfer_earnings', 'logistics_company_id')) {
            DB::table('hub_transfer_earnings')
                ->where('logistics_company_id', '!=', 1)
                ->update(['logistics_company_id' => 1]);
        }

        // Deactivate and soft-delete all other logistics companies
        $now = now();
        DB::table('logistics_companies')
            ->where('id', '!=', 1)
            ->update([
                'status' => 'inactive',
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse is not destructive; restores company 2..6 statuses if needed
    }
};

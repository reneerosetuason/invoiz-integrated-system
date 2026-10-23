<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The seller approval workflow (Admin\SellerController, SellerApplication
 * controller, Seller::approved() scope) reads and writes
 * sellers.approval_status, but the column was never migrated. Add it and
 * derive sensible values from the legacy `status` column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sellers', 'approval_status')) {
            DB::statement("ALTER TABLE `sellers` ADD COLUMN `approval_status` VARCHAR(50) NULL AFTER `status`");
        }

        DB::statement("UPDATE `sellers` SET `approval_status` = CASE
            WHEN `approval_status` IS NOT NULL AND `approval_status` <> '' THEN `approval_status`
            WHEN `status` IN ('pending','rejected','approved') THEN `status`
            ELSE 'approved' END");
    }

    public function down(): void
    {
        if (Schema::hasColumn('sellers', 'approval_status')) {
            Schema::table('sellers', function (Blueprint $table) {
                $table->dropColumn('approval_status');
            });
        }
    }
};

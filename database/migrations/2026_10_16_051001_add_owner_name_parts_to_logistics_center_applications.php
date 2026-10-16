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
        // Owner name split (first / middle / last). Raw statements: no dbal
        // needed, and safe to run via --path on databases whose migration
        // history is out of sync. owner_name is kept for back-compat rows.
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE `logistics_center_applications` ADD COLUMN `owner_first_name` VARCHAR(100) NULL AFTER `owner_name`, ADD COLUMN `owner_middle_name` VARCHAR(100) NULL AFTER `owner_first_name`, ADD COLUMN `owner_last_name` VARCHAR(100) NULL AFTER `owner_middle_name`");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE `logistics_center_applications` DROP COLUMN `owner_first_name`, DROP COLUMN `owner_middle_name`, DROP COLUMN `owner_last_name`");
    }
};

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
        // Registration fields for the logistics account black-box suite
        // (SEC/TIN/postal/region). Raw statements: no dbal needed, safe via
        // --path on databases whose migration history is out of sync.
        // No UNIQUE indexes: resubmission after a rejection must stay
        // possible, so uniqueness (ignoring rejected rows) lives in
        // validation, not in the schema.
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE `logistics_center_applications` ADD COLUMN `sec_number` VARCHAR(20) NULL AFTER `owner_last_name`, ADD COLUMN `tin` VARCHAR(20) NULL AFTER `sec_number`, ADD COLUMN `postal_code` VARCHAR(10) NULL AFTER `tin`, ADD COLUMN `region` VARCHAR(50) NULL AFTER `postal_code`");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE `logistics_center_applications` DROP COLUMN `sec_number`, DROP COLUMN `tin`, DROP COLUMN `postal_code`, DROP COLUMN `region`");
    }
};

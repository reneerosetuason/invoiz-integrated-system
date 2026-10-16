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
        // Buyer registration writes approval_status, but the shared users
        // table never had the column (registrations 500'd). Nullable so all
        // existing rows stay untouched. Raw statement: no dbal needed.
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE `users` ADD COLUMN `approval_status` VARCHAR(50) NULL AFTER `account_status`");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE `users` DROP COLUMN `approval_status`");
    }
};

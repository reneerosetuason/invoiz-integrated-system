<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN account_status ENUM('active', 'suspended', 'deactivated', 'pending_deletion', 'deletion_blocked', 'archived') DEFAULT 'active'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN account_status ENUM('active', 'suspended', 'deactivated') DEFAULT 'active'");
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Allow a `draft` product status. Drafts are used by sellers whose account
 * is still pending approval (they may prepare listings, but nothing is
 * published until they are approved).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        try {
            DB::statement("ALTER TABLE `products` MODIFY `status` ENUM('active','inactive','out_of_stock','draft') NOT NULL DEFAULT 'active'");
        } catch (\Throwable $e) {
            // Non-MySQL or already modified — safe to ignore.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        try {
            DB::statement("ALTER TABLE `products` MODIFY `status` ENUM('active','inactive','out_of_stock') NOT NULL DEFAULT 'active'");
        } catch (\Throwable $e) {
        }
    }
};

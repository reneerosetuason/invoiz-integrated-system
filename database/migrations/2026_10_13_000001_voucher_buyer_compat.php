<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/*
 * Voucher buyer-compat layer for fresh ecommerce DBs.
 * Adds buyer-app columns expected by App\Models\Voucher when they are missing.
 * Additive only: never drops or renames anything.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            if (! Schema::hasColumn('vouchers', 'name')) {
                $table->string('name', 150)->nullable()->after('code');
            }
            if (! Schema::hasColumn('vouchers', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            if (! Schema::hasColumn('vouchers', 'discount_type')) {
                $table->enum('discount_type', ['fixed', 'percent'])->default('fixed')->after('type');
            }
            if (! Schema::hasColumn('vouchers', 'discount_value')) {
                $table->decimal('discount_value', 10, 2)->nullable()->after('value');
            }
            if (! Schema::hasColumn('vouchers', 'max_discount')) {
                $table->decimal('max_discount', 10, 2)->nullable()->after('min_spend');
            }
            if (! Schema::hasColumn('vouchers', 'valid_from')) {
                $table->date('valid_from')->nullable()->after('ends_at');
            }
            if (! Schema::hasColumn('vouchers', 'valid_until')) {
                $table->date('valid_until')->nullable()->after('valid_from');
            }
        });

        // Normalize status to string enum so both bool (admin) and
        // 'active'/'inactive' (buyer + Voucher model hook) work.
        // vouchers table is empty on fresh DBs, so this is a no-data-loss change.
        try {
            $col = DB::selectOne(
                "SELECT COLUMN_TYPE, DATA_TYPE FROM information_schema.columns " .
                "WHERE table_schema = DATABASE() AND table_name = 'vouchers' AND column_name = 'status'"
            );
            $type = strtolower($col->COLUMN_TYPE ?? $col->DATA_TYPE ?? '');
            $isBool = str_contains($type, 'tinyint');
            if ($isBool) {
                DB::statement("ALTER TABLE `vouchers` MODIFY `status` VARCHAR(20) NOT NULL DEFAULT 'active'");
                DB::statement("UPDATE `vouchers` SET `status` = 'active' WHERE `status` IN ('1', '1.0')");
                DB::statement("UPDATE `vouchers` SET `status` = 'inactive' WHERE `status` IN ('0', '0.0')");
            }
        } catch (\Throwable $e) {
            // non-fatal: leave status as-is
        }

        // Backfill buyer columns from admin columns where possible.
        try {
            DB::statement("UPDATE `vouchers` SET `discount_type` = `type` WHERE (`discount_type` IS NULL OR `discount_type` = '')");
            DB::statement("UPDATE `vouchers` SET `discount_value` = `value` WHERE `discount_value` IS NULL AND `value` IS NOT NULL");
            DB::statement("UPDATE `vouchers` SET `name` = `code` WHERE (`name` IS NULL OR `name` = '')");
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        // non-destructive: leave compat columns as-is
    }
};

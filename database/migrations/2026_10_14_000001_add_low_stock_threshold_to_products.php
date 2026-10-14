<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-product low-stock alert level, set by the seller when adding or
 * editing a product. Defaults to 5 to preserve the previous behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'low_stock_threshold')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedInteger('low_stock_threshold')->default(5)->after('stock');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'low_stock_threshold')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('low_stock_threshold');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_items', 'product_stock_deducted')) {
            Schema::table('order_items', fn (Blueprint $table) => $table->boolean('product_stock_deducted')->default(false));
        }
    }

    public function down(): void
    {
        // Keep the stock-movement history needed to restore old orders safely.
    }
};

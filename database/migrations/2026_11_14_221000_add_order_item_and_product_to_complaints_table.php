<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            if (! Schema::hasColumn('complaints', 'order_item_id')) {
                $table->foreignId('order_item_id')->nullable()->after('order_id')->constrained('order_items')->nullOnDelete();
            }
            if (! Schema::hasColumn('complaints', 'product_id')) {
                $table->foreignId('product_id')->nullable()->after('order_item_id')->constrained('products')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            if (Schema::hasColumn('complaints', 'product_id')) {
                $table->dropForeign(['product_id']);
                $table->dropColumn('product_id');
            }
            if (Schema::hasColumn('complaints', 'order_item_id')) {
                $table->dropForeign(['order_item_id']);
                $table->dropColumn('order_item_id');
            }
        });
    }
};

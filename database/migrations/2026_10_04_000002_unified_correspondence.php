<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correspondence layer: makes buyer / seller / admin items match.
 *
 * Real invoizdb (buyer dump) uses one naming, admin migrations use another.
 * Every change is ADD-if-missing + backfill, never drops anything, so it is
 * safe on the shared `invoizdb` and on a fresh database.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- products: buyer cols missing from admin migrations ----
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'stock')) $table->integer('stock')->default(0);
            if (! Schema::hasColumn('products', 'image')) $table->string('image')->nullable();
            if (! Schema::hasColumn('products', 'compare_at_price')) $table->decimal('compare_at_price', 12, 2)->nullable();
            if (! Schema::hasColumn('products', 'rating')) $table->decimal('rating', 3, 2)->nullable();
            if (! Schema::hasColumn('products', 'model')) $table->string('model')->nullable();
            if (! Schema::hasColumn('products', 'material')) $table->string('material')->nullable();
            if (! Schema::hasColumn('products', 'dimensions')) $table->string('dimensions')->nullable();
            if (! Schema::hasColumn('products', 'weight')) $table->string('weight')->nullable();
            if (! Schema::hasColumn('products', 'warranty')) $table->string('warranty')->nullable();
            if (! Schema::hasColumn('products', 'origin')) $table->string('origin')->nullable();
        });

        // ---- categories: buyer uses status/image; admin uses active/slug ----
        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'status')) $table->string('status', 20)->default('active');
            if (! Schema::hasColumn('categories', 'image')) $table->string('image')->nullable();
        });
        try {
            \Illuminate\Support\Facades\DB::statement("UPDATE `categories` SET `status`='active' WHERE `status` IS NULL OR `status`=''");
            \Illuminate\Support\Facades\DB::statement("UPDATE `categories` SET `active`=1 WHERE `status`='active'");
        } catch (\Throwable $e) {}

        // ---- product_images: buyer uses image_path/sort_order ----
        Schema::table('product_images', function (Blueprint $table) {
            if (! Schema::hasColumn('product_images', 'image_path')) $table->string('image_path')->nullable();
            if (! Schema::hasColumn('product_images', 'sort_order')) $table->integer('sort_order')->default(0);
        });
        try {
            \Illuminate\Support\Facades\DB::statement("UPDATE `product_images` SET `image_path`=`path` WHERE (`image_path` IS NULL OR `image_path`='') AND `path` IS NOT NULL");
            \Illuminate\Support\Facades\DB::statement("UPDATE `product_images` SET `path`=`image_path` WHERE (`path` IS NULL OR `path`='') AND `image_path` IS NOT NULL");
        } catch (\Throwable $e) {}

        // ---- product_variants: buyer needs status/image/variant_type... ----
        Schema::table('product_variants', function (Blueprint $table) {
            if (! Schema::hasColumn('product_variants', 'variant_type')) $table->string('variant_type')->default('Default');
            if (! Schema::hasColumn('product_variants', 'variant_value')) $table->string('variant_value')->default('Default');
            if (! Schema::hasColumn('product_variants', 'price_adjustment')) $table->decimal('price_adjustment', 12, 2)->default(0);
            if (! Schema::hasColumn('product_variants', 'image')) $table->string('image')->nullable();
            if (! Schema::hasColumn('product_variants', 'status')) $table->string('status', 20)->default('active');
        });

        // ---- orders: buyer cols (address_id/notes) on admin DBs ----
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'address_id')) $table->unsignedBigInteger('address_id')->nullable();
            if (! Schema::hasColumn('orders', 'notes')) $table->text('notes')->nullable();
            if (! Schema::hasColumn('orders', 'total_amount')) $table->decimal('total_amount', 12, 2)->default(0);
        });
        try {
            \Illuminate\Support\Facades\DB::statement("UPDATE `orders` SET `total_amount`=`total` WHERE (`total_amount` IS NULL OR `total_amount`=0)");
            \Illuminate\Support\Facades\DB::statement("UPDATE `orders` SET `total`=`total_amount` WHERE (`total` IS NULL OR `total`=0)");
        } catch (\Throwable $e) {}

        // ---- order_items: buyer cols on admin DBs ----
        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'seller_id')) $table->unsignedBigInteger('seller_id')->nullable();
            if (! Schema::hasColumn('order_items', 'product_name')) $table->string('product_name')->nullable();
            if (! Schema::hasColumn('order_items', 'variant_label')) $table->string('variant_label')->nullable();
            if (! Schema::hasColumn('order_items', 'price')) $table->decimal('price', 12, 2)->nullable();
            if (! Schema::hasColumn('order_items', 'subtotal')) $table->decimal('subtotal', 12, 2)->nullable();
            if (! Schema::hasColumn('order_items', 'variant_id')) $table->unsignedBigInteger('variant_id')->nullable();
        });
        try {
            \Illuminate\Support\Facades\DB::statement("UPDATE `order_items` SET `price`=`unit_price` WHERE `price` IS NULL AND `unit_price` IS NOT NULL");
            \Illuminate\Support\Facades\DB::statement("UPDATE `order_items` SET `unit_price`=`price` WHERE `unit_price` IS NULL AND `price` IS NOT NULL");
            \Illuminate\Support\Facades\DB::statement("UPDATE `order_items` SET `subtotal`=`total_price` WHERE `subtotal` IS NULL AND `total_price` IS NOT NULL");
            \Illuminate\Support\Facades\DB::statement("UPDATE `order_items` SET `total_price`=`subtotal` WHERE `total_price` IS NULL AND `subtotal` IS NOT NULL");
        } catch (\Throwable $e) {}

        // ---- messages: admin needs receiver_id + conversation_id ----
        Schema::table('messages', function (Blueprint $table) {
            if (! Schema::hasColumn('messages', 'receiver_id')) $table->unsignedBigInteger('receiver_id')->nullable();
            if (! Schema::hasColumn('messages', 'conversation_id')) $table->unsignedBigInteger('conversation_id')->nullable();
        });

        // ---- reviews (buyer) must exist even on admin-fresh DBs ----
        if (! Schema::hasTable('reviews')) {
            Schema::create('reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->unsignedTinyInteger('rating')->default(5);
                $table->text('comment')->nullable();
                $table->string('status', 20)->default('visible');
                $table->timestamps();
            });
        }

        // ---- sellers: buyer cols on admin DBs ----
        Schema::table('sellers', function (Blueprint $table) {
            if (! Schema::hasColumn('sellers', 'business_name')) $table->string('business_name')->nullable();
            if (! Schema::hasColumn('sellers', 'line_of_business')) $table->string('line_of_business')->nullable();
            if (! Schema::hasColumn('sellers', 'id_image')) $table->string('id_image')->nullable();
            if (! Schema::hasColumn('sellers', 'business_permit')) $table->string('business_permit')->nullable();
        });
    }

    public function down(): void {}
};

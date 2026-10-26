<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seller store sales: a date-windowed discount applied to a set of the
 * seller's products. A seller may run only ONE sale at a time.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales')) {
            Schema::create('sales', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('seller_id')->nullable()->index();
                $table->string('name');
                $table->string('type')->default('store');       // store | flash
                $table->string('discount_type')->default('percent'); // percent | fixed
                $table->decimal('discount_value', 10, 2)->default(0);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('product_sales')) {
            Schema::create('product_sales', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sale_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->decimal('sale_price', 10, 2)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sales');
        Schema::dropIfExists('sales');
    }
};

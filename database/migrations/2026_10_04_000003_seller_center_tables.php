<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seller-center tables from the original seller app.
 * Guarded (create only if missing) so the shared invoizdb is untouched
 * when they already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('seller_notifications')) {
            Schema::create('seller_notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('seller_id');
                $table->unsignedBigInteger('order_id')->nullable();
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('type', 50)->default('info');
                $table->string('title');
                $table->text('body')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamps();
                $table->index('seller_id');
            });
        }

        if (! Schema::hasTable('courier_pickups')) {
            Schema::create('courier_pickups', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('seller_id');
                $table->string('courier', 100);
                $table->dateTime('pickup_at')->nullable();
                $table->string('tracking_number', 100)->nullable();
                $table->string('status', 50)->default('scheduled');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['order_id', 'seller_id']);
            });
        }
    }

    public function down(): void {}
};

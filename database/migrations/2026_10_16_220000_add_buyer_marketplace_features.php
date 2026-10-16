<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vouchers')) {
            Schema::table('vouchers', function (Blueprint $table) {
                if (! Schema::hasColumn('vouchers', 'name')) $table->string('name', 150)->nullable();
                if (! Schema::hasColumn('vouchers', 'description')) $table->text('description')->nullable();
                if (! Schema::hasColumn('vouchers', 'discount_value')) $table->decimal('discount_value', 10, 2)->default(0);
                if (! Schema::hasColumn('vouchers', 'max_discount')) $table->decimal('max_discount', 10, 2)->nullable();
                if (! Schema::hasColumn('vouchers', 'valid_from')) $table->dateTime('valid_from')->nullable();
                if (! Schema::hasColumn('vouchers', 'valid_until')) $table->dateTime('valid_until')->nullable();
            });
            try {
                \Illuminate\Support\Facades\DB::statement("UPDATE `vouchers` SET `discount_value` = `value` WHERE (`discount_value` IS NULL OR `discount_value` = 0) AND `value` IS NOT NULL");
                \Illuminate\Support\Facades\DB::statement("UPDATE `vouchers` SET `discount_type` = `type` WHERE `discount_type` IS NULL OR `discount_type` = ''");
            } catch (\Throwable $e) {}
        }

        if (! Schema::hasTable('favorites')) {
            Schema::create('favorites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['buyer_id', 'product_id']);
            });
        }

        if (! Schema::hasTable('voucher_claims')) {
            Schema::create('voucher_claims', function (Blueprint $table) {
                $table->id();
                $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('voucher_id')->constrained('vouchers')->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->timestamp('claimed_at')->useCurrent();
                $table->timestamp('used_at')->nullable();
                $table->timestamps();
                $table->unique(['buyer_id', 'voucher_id']);
            });
        }

        if (! Schema::hasTable('conversations')) {
            Schema::create('conversations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('subject')->nullable();
                $table->timestamps();
                $table->index(['buyer_id', 'seller_id']);
            });
        }

        if (! Schema::hasTable('notification_reads')) {
            Schema::create('notification_reads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('notification_key', 191);
                $table->timestamps();
                $table->unique(['user_id', 'notification_key']);
            });
        }

        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'cancellation_reason')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('cancellation_reason', 500)->nullable();
            });
        }
    }

    public function down(): void
    {
        // Shared invoizdb migrations are intentionally non-destructive.
    }
};

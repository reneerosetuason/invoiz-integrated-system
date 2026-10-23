<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The marketplace code (order vouchers, waybill payment block) still reads
 * the legacy `payments` and `order_vouchers` tables, but neither was ever
 * created by a migration in this database. Mirror the invoizdb schema so
 * those code paths stop throwing "table not found".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->index();
                $table->enum('method', ['cash_on_delivery', 'gcash', 'bank_transfer']);
                $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
                $table->decimal('amount', 10, 2);
                $table->string('reference_number', 100)->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('order_vouchers')) {
            Schema::create('order_vouchers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->index();
                $table->unsignedBigInteger('voucher_id')->index();
                $table->decimal('discount_amount', 10, 2)->default(0);
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_vouchers');
        Schema::dropIfExists('payments');
    }
};

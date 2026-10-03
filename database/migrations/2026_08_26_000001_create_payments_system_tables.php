<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->unique()->constrained('categories')->nullOnDelete();
            $table->decimal('rate', 5, 2)->default(10.00);
            $table->timestamps();
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('transaction_ref')->unique();
            $table->string('gateway')->default('GCash');
            $table->decimal('amount', 12, 2)->default(0);
            $table->enum('status', ['successful', 'pending', 'failed', 'refunded', 'partially_refunded'])->default('pending');
            $table->decimal('seller_earning', 12, 2)->default(0);
            $table->decimal('platform_commission', 12, 2)->default(0);
            $table->decimal('rider_fee', 12, 2)->default(0);
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('seller_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->enum('status', ['pending', 'processing', 'paid'])->default('pending');
            $table->string('method')->default('Bank Transfer');
            $table->string('reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('method')->default('Bank Transfer');
            $table->text('note')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('reason');
            $table->enum('status', ['open', 'under_review', 'resolved', 'rejected'])->default('open');
            $table->text('resolution_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_disputes');
        Schema::dropIfExists('withdrawal_requests');
        Schema::dropIfExists('seller_payouts');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('commission_rates');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Buyer return/refund requests. A buyer asks to return a delivered item;
 * the seller approves or rejects; once the item is received back the order
 * is refunded (through the payment ledger) and stock is restored.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('return_requests')) {
            return;
        }

        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('reason');
            $table->text('reason_details')->nullable();
            $table->decimal('refund_amount', 12, 2)->default(0);
            $table->enum('status', ['requested', 'approved', 'rejected', 'item_received', 'refunded', 'cancelled'])->default('requested');
            $table->text('decision_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['seller_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_requests');
    }
};

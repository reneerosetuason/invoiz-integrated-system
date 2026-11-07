<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('return_settlements', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('return_request_id')->unique();
            $t->unsignedBigInteger('order_id')->index();
            $t->string('fault')->default('unresolved');
            $t->string('resolution')->default('return_refund');
            $t->string('payer')->default('seller');
            $t->string('funding_method')->default('deposit');
            $t->string('status')->default('draft');
            $t->unsignedInteger('version')->default(1);
            foreach (['maximum_refund', 'merchandise', 'shipping', 'transport', 'compensation', 'net_refund', 'reserved'] as $amount) {
                $t->unsignedBigInteger($amount.'_cents')->default(0);
            }
            $t->unsignedBigInteger('collection_center_id')->nullable();
            $t->unsignedBigInteger('seller_center_id')->nullable();
            $t->text('reason_note')->nullable();
            $t->text('decision_note')->nullable();
            $t->text('dispute_note')->nullable();
            $t->text('refund_destination')->nullable();
            $t->json('events')->nullable();
            foreach (['buyer_accepted', 'seller_accepted', 'admin_decided', 'quoted', 'funded', 'destination_verified', 'collection', 'response_due', 'closed'] as $date) {
                $t->timestamp($date.'_at')->nullable();
            }
            $t->string('replacement_reference', 150)->nullable()->unique();
            $t->unsignedBigInteger('replacement_product_id')->nullable();
            $t->unsignedBigInteger('replacement_variant_id')->nullable();
            $t->text('replacement_description')->nullable();
            $t->timestamp('replacement_stock_reserved_at')->nullable();
            $t->timestamp('replacement_stock_restored_at')->nullable();
            $t->string('replacement_proof_path')->nullable();
            $t->timestamps();
        });
        Schema::create('return_fund_payments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('return_settlement_id')->index();
            $t->unsignedBigInteger('payer_user_id');
            $t->string('purpose');
            $t->unsignedBigInteger('amount_cents');
            $t->unsignedBigInteger('returned_cents')->default(0);
            $t->string('reference', 150)->unique();
            $t->string('proof_path');
            $t->string('status')->default('submitted');
            $t->unsignedBigInteger('reviewed_by')->nullable();
            $t->text('review_note')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
        });
        Schema::create('return_allocations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('return_settlement_id')->index();
            $t->unsignedBigInteger('fund_payment_id')->nullable()->index();
            $t->string('role');
            $t->unsignedBigInteger('amount_cents');
            $t->unsignedBigInteger('center_id')->nullable()->index();
            $t->unsignedBigInteger('recipient_user_id')->nullable()->index();
            $t->unsignedBigInteger('rider_id')->nullable();
            $t->unsignedBigInteger('driver_id')->nullable()->index();
            $t->string('status')->default('reserved');
            $t->string('reference', 150)->nullable()->unique();
            $t->string('proof_path')->nullable();
            $t->text('receiving_destination')->nullable();
            $t->unsignedBigInteger('remitted_by')->nullable();
            foreach (['eligible', 'remitted', 'accepted'] as $date) $t->timestamp($date.'_at')->nullable();
            $t->unsignedBigInteger('accepted_by')->nullable();
            $t->text('note')->nullable();
            $t->timestamps();
            $t->unique(['return_settlement_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_allocations');
        Schema::dropIfExists('return_fund_payments');
        Schema::dropIfExists('return_settlements');
    }
};

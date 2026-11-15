<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_delivery_cases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_id')->unique();
            $table->unsignedBigInteger('seller_id')->index();
            $table->unsignedBigInteger('center_id');
            $table->unsignedBigInteger('seller_center_id')->nullable();
            $table->string('decision')->nullable();
            $table->string('stage')->default('seller_review')->index();
            $table->json('quote')->nullable();
            $table->json('events')->nullable();
            $table->unsignedBigInteger('return_driver_id')->nullable();
            $table->unsignedBigInteger('return_rider_id')->nullable();
            $table->unsignedBigInteger('truck_id')->nullable();
            $table->timestamp('quoted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('funded_at')->nullable();
            $table->timestamp('driver_accepted_at')->nullable();
            $table->timestamp('driver_arrived_at')->nullable();
            $table->string('handover_proof_path')->nullable();
            $table->string('driver_proof_path')->nullable();
            $table->string('seller_proof_path')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('failed_delivery_fees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('failed_delivery_case_id')->index();
            $table->string('service_key');
            $table->string('role');
            $table->string('leg');
            $table->unsignedBigInteger('rider_id')->nullable();
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->unsignedBigInteger('center_id')->nullable();
            $table->unsignedBigInteger('recipient_user_id')->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('credited_cents')->default(0);
            $table->string('status')->default('reserved');
            $table->timestamp('fulfilled_at')->nullable();
            $table->string('reference')->nullable()->unique();
            $table->string('proof_path')->nullable();
            $table->unsignedBigInteger('remitted_by')->nullable();
            $table->timestamp('remitted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['failed_delivery_case_id', 'service_key'], 'failed_delivery_fee_service_unique');
        });
        Schema::create('failed_delivery_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('failed_delivery_case_id')->index();
            $table->unsignedBigInteger('payer_user_id');
            $table->unsignedBigInteger('amount_cents');
            $table->string('reference')->unique();
            $table->string('proof_path');
            $table->string('status')->default('submitted');
            $table->unsignedBigInteger('receipt_viewed_by')->nullable();
            $table->timestamp('receipt_viewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('review_note')->nullable();
            $table->json('revisions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_delivery_payments');
        Schema::dropIfExists('failed_delivery_fees');
        Schema::dropIfExists('failed_delivery_cases');
    }
};

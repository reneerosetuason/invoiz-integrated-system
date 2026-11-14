<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('parcel_liabilities')) {
            return;
        }

        Schema::create('parcel_liabilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('delivery_id')->index();
            $table->unsignedBigInteger('seller_id')->index();
            $table->unsignedBigInteger('logistics_company_id')->nullable()->index();
            $table->unsignedBigInteger('logistics_center_id')->nullable()->index();
            $table->unsignedBigInteger('rider_id')->nullable()->index();
            $table->string('custody_classification', 50)->default('undetermined');
            $table->decimal('merchandise_amount', 12, 2)->default(0);
            $table->string('status', 30)->default('proposed')->index();
            $table->string('damage_evidence_path')->nullable();
            $table->timestamp('proposed_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->unsignedBigInteger('settled_by')->nullable();
            $table->string('settlement_reference', 150)->nullable();
            $table->timestamp('disputed_at')->nullable();
            $table->string('dispute_reason')->nullable();
            $table->text('dispute_notes')->nullable();
            $table->string('dispute_evidence_path')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->string('resolution_outcome', 30)->nullable();
            $table->text('resolution_notes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcel_liabilities');
    }
};

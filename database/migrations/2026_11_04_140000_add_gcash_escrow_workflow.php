<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->string('gcash_qr_path')->nullable();
            $table->string('gcash_account_name', 150)->nullable();
        });
        Schema::create('gcash_escrow_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('receipt_pending')->index();
            $table->timestamp('receipt_verified_at')->nullable();
            $table->string('reference', 150)->nullable()->unique();
            $table->string('proof_path')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamps();
        });
        Schema::create('gcash_escrow_settings', function (Blueprint $table) {
            $table->id();
            $table->string('account_name', 150);
            $table->string('account_details', 500);
            $table->string('qr_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Financial records must be retained. Use a reviewed forward migration.');
    }
};

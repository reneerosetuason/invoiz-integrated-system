<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('direct_refund_transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('return_request_id')->index();
            $table->string('reference', 150)->unique();
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedInteger('revision')->default(1);
            $table->string('status')->default('pending');
            $table->text('receipt_versions');
            $table->text('buyer_note')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('direct_refund_transfers'); }
};

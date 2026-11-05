<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('payment_disputes')) {
            Schema::table('payment_disputes', fn (Blueprint $table) => $table->string('status', 32)->default('open')->change());
        }
        Schema::create('return_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->unique()->constrained('return_requests');
            $table->unsignedBigInteger('center_id')->nullable()->index();
            $table->unsignedBigInteger('destination_center_id')->nullable();
            $table->unsignedBigInteger('rider_id')->nullable()->index();
            $table->string('stage')->default('awaiting_collection');
            $table->string('transport_reference', 150)->nullable();
            $table->boolean('stock_restored')->default(false);
            $table->string('refund_reference', 150)->nullable()->unique();
            $table->string('refund_proof_path')->nullable();
            $table->decimal('recovery_amount', 12, 2)->default(0);
            $table->string('recovery_reference', 150)->nullable()->unique();
            $table->string('recovery_proof_path')->nullable();
            $table->timestamp('refund_sent_at')->nullable();
            $table->timestamp('buyer_confirmed_at')->nullable();
            $table->json('events')->nullable();
            $table->timestamps();
        });
        // Preserve earlier decisions without pretending that any new transport occurred.
        \Illuminate\Support\Facades\DB::table('return_requests')->whereIn('status', ['approved', 'item_received'])->orderBy('id')->each(function ($case) {
            $delivery = \Illuminate\Support\Facades\DB::table('deliveries')->where('order_id', $case->order_id)->where('seller_id', $case->seller_id)->first();
            \Illuminate\Support\Facades\DB::table('return_workflows')->insert([
                'return_request_id' => $case->id, 'center_id' => $delivery->destination_center_id ?? $delivery->center_id ?? null,
                'stage' => $case->status === 'item_received' ? 'refund_pending' : 'awaiting_collection',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }
    public function down(): void { Schema::dropIfExists('return_workflows'); }
};

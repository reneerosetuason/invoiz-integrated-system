<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            // Full rider delivery workflow statuses
            $table->enum('status', [
                'waiting_for_rider',
                'assigned',
                'accepted',
                'going_to_pickup',
                'arrived_at_shop',
                'picked_up',
                'out_for_delivery',
                'arrived_at_customer',
                'delivered',
                'delivery_failed',
                'cancelled',
            ])->default('waiting_for_rider')->change();

            // Payment / COD
            $table->string('payment_method')->nullable()->after('delivery_notes');
            $table->decimal('amount_to_collect', 12, 2)->nullable()->after('payment_method');
            $table->decimal('delivery_fee', 12, 2)->nullable()->after('amount_to_collect');
            $table->string('pickup_pin', 10)->nullable()->after('delivery_fee');

            // Coordinates
            $table->decimal('sender_lat', 10, 7)->nullable()->after('pickup_pin');
            $table->decimal('sender_lng', 10, 7)->nullable()->after('sender_lat');
            $table->decimal('recipient_lat', 10, 7)->nullable()->after('sender_lng');
            $table->decimal('recipient_lng', 10, 7)->nullable()->after('recipient_lat');

            // Workflow timestamps
            $table->timestamp('accepted_at')->nullable()->after('delivered_at');
            $table->timestamp('cancelled_at')->nullable()->after('accepted_at');
            $table->timestamp('failed_at')->nullable()->after('cancelled_at');
            $table->string('failure_reason')->nullable()->after('failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->enum('status', [
                'waiting_for_rider',
                'assigned',
                'picked_up',
                'out_for_delivery',
                'delivered',
                'failed',
            ])->default('waiting_for_rider')->change();

            $table->dropColumn([
                'payment_method',
                'amount_to_collect',
                'delivery_fee',
                'pickup_pin',
                'sender_lat',
                'sender_lng',
                'recipient_lat',
                'recipient_lng',
                'accepted_at',
                'cancelled_at',
                'failed_at',
                'failure_reason',
            ]);
        });
    }
};
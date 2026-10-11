<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Make order_id optional so standalone logistics deliveries (no e-commerce order) can exist
        Schema::table('deliveries', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->change();
        });

        // Drop the rider_id FK that points to users() — the logistics app assigns
        // riders.id here. Keep the plain column so both apps can use it.
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropForeign('fk_deliveries_rider');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            // Logistics columns
            $table->string('tracking_number')->nullable()->unique()->after('id');
            $table->string('sender_name')->nullable();
            $table->string('sender_phone')->nullable();
            $table->text('sender_address')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->text('recipient_address')->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->text('notes')->nullable();

            // Plain index for rider lookups
            $table->index('rider_id');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropIndex(['rider_id']);
            $table->dropColumn([
                'tracking_number', 'sender_name', 'sender_phone', 'sender_address',
                'recipient_name', 'recipient_phone', 'recipient_address',
                'weight', 'notes',
            ]);
            $table->unsignedBigInteger('order_id')->nullable(false)->change();
            $table->foreign('rider_id')->references('id')->on('users')->nullOnDelete();
        });
    }
};
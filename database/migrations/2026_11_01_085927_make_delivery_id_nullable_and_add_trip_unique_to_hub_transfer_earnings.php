<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('hub_transfer_earnings', function (Blueprint $table) {
            $table->unsignedBigInteger('delivery_id')->nullable()->change();

            // Trip-level idempotency: one compensation record per transfer and recipient role
            $table->unique(['hub_transfer_id', 'recipient_role'], 'unique_hub_transfer_trip_role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hub_transfer_earnings', function (Blueprint $table) {
            $table->dropUnique('unique_hub_transfer_trip_role');
            $table->unsignedBigInteger('delivery_id')->nullable(false)->change();
        });
    }
};

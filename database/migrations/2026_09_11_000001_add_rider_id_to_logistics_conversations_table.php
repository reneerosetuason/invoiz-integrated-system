<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The rider scoping column was referenced by the code (conversation list
     * and authorization) but never created, which made the per-delivery
     * conversation endpoints throw. Additive; existing support threads
     * (participant_type=rider) keep working.
     */
    public function up(): void
    {
        Schema::table('logistics_conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('rider_id')->nullable()->after('order_id');
            $table->index('rider_id');
        });
    }

    public function down(): void
    {
        Schema::table('logistics_conversations', function (Blueprint $table) {
            $table->dropIndex(['rider_id']);
            $table->dropColumn('rider_id');
        });
    }
};
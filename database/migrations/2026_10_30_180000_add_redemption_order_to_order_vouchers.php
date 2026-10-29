<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_vouchers', function (Blueprint $table) {
            // The first order in one redemption, shared by its store allocations.
            // A reused claim receives a new anchor, preserving cancellation history.
            $table->unsignedBigInteger('redemption_order_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('order_vouchers', function (Blueprint $table) {
            $table->dropColumn('redemption_order_id');
        });
    }
};

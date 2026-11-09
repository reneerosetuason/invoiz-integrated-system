<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('return_settlements', function (Blueprint $table) {
            // Null preserves the funding choice of an existing agreement.
            $table->string('funding_preference')->nullable();
            $table->unsignedBigInteger('seller_reserved_cents')->default(0);
            $table->unsignedBigInteger('refund_reserved_cents')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('return_settlements', fn (Blueprint $table) => $table->dropColumn([
            'funding_preference', 'seller_reserved_cents', 'refund_reserved_cents',
        ]));
    }
};

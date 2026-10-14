<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allow the free_shipping voucher type: the seller waives the buyer's
 * per-shop shipping fee and absorbs it out of their own sales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->enum('discount_type', ['fixed', 'percent', 'free_shipping'])->default('fixed')->change();
            $table->enum('type', ['fixed', 'percent', 'free_shipping'])->default('fixed')->change();
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->enum('discount_type', ['fixed', 'percent'])->default('fixed')->change();
            $table->enum('type', ['fixed', 'percent'])->default('fixed')->change();
        });
    }
};

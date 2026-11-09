<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('return_fund_payments',function(Blueprint $table){
            $table->unsignedBigInteger('receipt_viewed_by')->nullable();
            $table->timestamp('receipt_viewed_at')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('return_fund_payments',fn(Blueprint $table)=>$table->dropColumn(['receipt_viewed_by','receipt_viewed_at']));
    }
};

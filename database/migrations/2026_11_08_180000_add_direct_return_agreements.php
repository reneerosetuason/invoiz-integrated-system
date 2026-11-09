<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('return_settlements',function(Blueprint $table){
            $table->string('workflow_policy')->default('legacy');
            $table->json('fee_snapshot')->nullable();
        });
        // Only untouched drafts adopt the new flow. Never rewrite paid terms.
        DB::table('return_settlements')->where('status','draft')->whereNull('funded_at')->whereNull('closed_at')
            ->where('reserved_cents',0)->where('seller_reserved_cents',0)->where('refund_reserved_cents',0)
            ->whereNotExists(fn($q)=>$q->selectRaw('1')->from('return_fund_payments')->whereColumn('return_settlement_id','return_settlements.id'))
            ->update(['workflow_policy'=>'direct','funding_preference'=>'deposit','funding_method'=>'deposit']);
    }
    public function down(): void
    {
        Schema::table('return_settlements',fn(Blueprint $table)=>$table->dropColumn(['workflow_policy','fee_snapshot']));
    }
};

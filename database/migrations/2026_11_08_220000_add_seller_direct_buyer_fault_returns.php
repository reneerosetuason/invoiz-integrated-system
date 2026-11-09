<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('return_settlements',function(Blueprint $t){
            $t->unsignedSmallInteger('policy_version')->default(1);
            $t->json('cost_snapshot')->nullable();
            $t->text('deduction_note')->nullable();
            $t->timestamp('collection_authorized_at')->nullable();
        });
        Schema::table('return_workflows',function(Blueprint $t){
            $t->string('refund_source')->default('platform_escrow');
            $t->unsignedBigInteger('refund_sender_user_id')->nullable();
        });
        // Preserve quoted/accepted/paid contracts; only untouched drafts adopt
        // the new policy automatically.
        \Illuminate\Support\Facades\DB::table('return_settlements')->where('status','draft')->whereNull('quoted_at')
            ->whereNull('buyer_accepted_at')->whereNull('seller_accepted_at')->whereNull('funded_at')
            ->whereNotExists(fn($q)=>$q->selectRaw('1')->from('return_fund_payments')->whereColumn('return_fund_payments.return_settlement_id','return_settlements.id'))
            ->update(['policy_version'=>2]);
    }
    public function down(): void
    {
        Schema::table('return_workflows',fn(Blueprint $t)=>$t->dropColumn(['refund_source','refund_sender_user_id']));
        Schema::table('return_settlements',fn(Blueprint $t)=>$t->dropColumn(['policy_version','cost_snapshot','deduction_note','collection_authorized_at']));
    }
};

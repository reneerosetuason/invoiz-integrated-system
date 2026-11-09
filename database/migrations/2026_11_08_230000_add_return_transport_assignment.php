<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
    public function up(): void
    {
        Schema::table('return_workflows',function(Blueprint $t){
            $t->unsignedBigInteger('driver_id')->nullable()->index();
            $t->timestamp('driver_accepted_at')->nullable();$t->timestamp('driver_arrived_at')->nullable();
        });
        DB::table('return_workflows')->where('stage','in_transfer')->orderBy('id')->each(function($flow){
            $driver=DB::table('return_allocations')->join('return_settlements','return_settlements.id','=','return_allocations.return_settlement_id')
                ->where('return_settlements.return_request_id',$flow->return_request_id)->where('role','inter_hub_driver')->value('driver_id');
            if($driver)DB::table('return_workflows')->where('id',$flow->id)->update(['driver_id'=>$driver]);
        });
    }
    public function down(): void {Schema::table('return_workflows',fn(Blueprint $t)=>$t->dropColumn(['driver_id','driver_accepted_at','driver_arrived_at']));}
};

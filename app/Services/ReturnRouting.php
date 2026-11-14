<?php

namespace App\Services;

use App\Models\{LogisticsCenter,ReturnRequest,ReturnSettlement,User};
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ReturnRouting
{
    public static function operational(?LogisticsCenter $hub): bool
    {
        return $hub && $hub->is_active && (!Schema::hasTable('logistics_companies') || $hub->company?->status==='active');
    }

    public static function options(): \Illuminate\Support\Collection
    {
        $q=LogisticsCenter::where('is_active',true)->orderBy('name');
        if(Schema::hasTable('logistics_companies'))$q->with('company');
        return $q->get()->filter(fn($hub)=>self::operational($hub));
    }

    /** A receiving-hub substitution may never add fees or reassign paid work. */
    public static function retarget(ReturnRequest $case,User $actor,int $target): void
    {
        $s=ReturnSettlement::where('return_request_id',$case->id)->lockForUpdate()->firstOrFail();
        if((int)$s->seller_center_id===$target)return;
        if ($s->fee_snapshot['return_trip'] ?? null) throw ValidationException::withMessages(['return' => 'The protected return trip has a funded distance and payment snapshot. Agree to a revised route and funding before changing its destination.']);
        $hub=LogisticsCenter::find($target);
        if(!self::operational($hub))throw ValidationException::withMessages(['return'=>'Choose an active destination hub from an active logistics company.']);
        if(!$s->collectionReady() || !$s->physicalReturn() || $case->workflow?->stage!=='at_hub'
            || (int)$case->workflow->center_id!==(int)$s->collection_center_id || $target===(int)$s->collection_center_id)
            throw ValidationException::withMessages(['return'=>'A destination change must keep the agreed return services and fees. A route needing additional services requires a revised agreement.']);
        $roles=['seller_hub','seller_delivery_rider','replacement_delivery_rider'];
        $lines=$s->allocations()->whereIn('role',$roles)->lockForUpdate()->get();
        if($lines->contains(fn($line)=>$line->status!=='reserved'))
            throw ValidationException::withMessages(['return'=>'Receiving work has already started or been paid. Its recipient cannot be changed.']);
        if((int)$s->seller_center_id===(int)$s->collection_center_id)
            throw ValidationException::withMessages(['return'=>'This adds an inter-hub service. Agree to the new delivery budget before changing this route.']);
        $old=(int)$s->seller_center_id;
        foreach($lines as $line)$line->update(['center_id'=>$target,'rider_id'=>null,'recipient_user_id'=>null,'receiving_destination'=>null]);
        $snapshot=$s->fee_snapshot;
        if($snapshot){
            $snapshot['seller_center_id']=$target;$snapshot['seller_hub_name']=$hub->name;
            foreach($snapshot['lines']??[] as $i=>$line)if(in_array($line['role'],$roles,true))$snapshot['lines'][$i]['center_id']=$target;
        }
        $events=$s->events??[];
        $events[]=['action'=>'destination_changed','actor_id'=>$actor->id,'actor_label'=>'Logistics','version'=>$s->version,'at'=>now()->toIso8601String(),
            'note'=>'Return destination changed from hub #'.$old.' to '.$hub->name.'. Agreed services and amounts are unchanged.'];
        $s->update(['seller_center_id'=>$target,'fee_snapshot'=>$snapshot,'events'=>$events]);
        $case->unsetRelation('settlement');
    }
}

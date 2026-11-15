<?php

namespace App\Support;

use App\Models\{ReturnAllocation,Rider};
use Illuminate\Support\{Collection,Facades\Schema};

/** Report accepted existing allocations, without creating another payout/earning. */
final class ReturnServiceEarnings
{
    public static function rider(Rider $rider): Collection
    {
        $returns=Schema::hasTable('return_allocations')?ReturnAllocation::with('settlement')->where('status','accepted')->where('rider_id',$rider->id)
            ->whereIn('role',['collection_rider','seller_delivery_rider','replacement_delivery_rider'])->get():collect();
        return $returns->concat(self::failed('rider_id',$rider->id));
    }
    public static function driver(int $id): Collection
    {
        $returns=Schema::hasTable('return_allocations')?ReturnAllocation::with('settlement')->where('status','accepted')->where('driver_id',$id)->where('role','inter_hub_driver')->get():collect();
        return $returns->concat(self::failed('driver_id',$id));
    }
    private static function failed(string $column,int $id): Collection
    {
        if(!Schema::hasTable('failed_delivery_fees'))return collect();
        return \App\Models\FailedDeliveryFee::where('status','accepted')->where($column,$id)->get()->reject(function($row)use($column,$id){
            if($column!=='rider_id' || $row->leg!=='outbound' || !Schema::hasTable('rider_earnings'))return false;
            $type=match($row->role){'pickup_rider'=>'pickup','last_mile_rider'=>'delivery',default=>null};
            return $type && \App\Models\RiderEarning::where('delivery_id',$row->resolution->delivery_id)
                ->where('rider_id',$id)->where('type',$type)->exists();
        })->map(function($row){
            $row->amount_cents=$row->payableCents();return $row;
        });
    }
    public static function amount(Collection $rows,?string $from=null): float
    {
        return $rows->filter(fn($row)=>$row->accepted_at && (!$from || $row->accepted_at->toDateString()>=$from))->sum('amount_cents')/100;
    }
}

<?php

namespace App\Support;

use App\Models\{ReturnAllocation,Rider};
use Illuminate\Support\{Collection,Facades\Schema};

/** Report accepted existing allocations, without creating another payout/earning. */
final class ReturnServiceEarnings
{
    public static function rider(Rider $rider): Collection
    {
        if(!Schema::hasTable('return_allocations'))return collect();
        return ReturnAllocation::with('settlement')->where('status','accepted')->where('rider_id',$rider->id)
            ->whereIn('role',['collection_rider','seller_delivery_rider','replacement_delivery_rider'])->get();
    }
    public static function driver(int $id): Collection
    {
        if(!Schema::hasTable('return_allocations'))return collect();
        return ReturnAllocation::with('settlement')->where('status','accepted')->where('driver_id',$id)->where('role','inter_hub_driver')->get();
    }
    public static function amount(Collection $rows,?string $from=null): float
    {
        return $rows->filter(fn($row)=>$row->accepted_at && (!$from || $row->accepted_at->toDateString()>=$from))->sum('amount_cents')/100;
    }
}

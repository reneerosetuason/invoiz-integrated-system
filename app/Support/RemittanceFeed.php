<?php

namespace App\Support;

use App\Models\{ReturnAllocation,EscrowRemittance,HubTransportDriver,User,LogisticsCenter};
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\{Collection,Facades\Schema};

final class RemittanceFeed
{
    public static function forRecipient($actor): Collection
    {
        $orders=collect();
        if(Schema::hasTable('escrow_remittances')){
            $q=EscrowRemittance::with(['order','delivery','logisticsCenter','hubTransfer']);
            $driver=$actor instanceof HubTransportDriver?$actor:($actor instanceof User && Schema::hasTable('hub_transport_drivers')?$actor->hubTransportDriver:null);
            if($driver)$q->where('recipient_role',EscrowRemittance::ROLE_INTER_HUB_DRIVER)->where('hub_transport_driver_id',$driver->id);
            elseif($actor->isAdmin())$q->where('recipient_role',EscrowRemittance::ROLE_ADMIN);
            elseif($actor->isLogisticsStaff()){
                $centers=LogisticsCenter::get()->filter(fn($center)=>!LogisticsAccess::isGlobal($actor) && LogisticsAccess::isCenterStaff($actor,$center->id)
                    || ($actor->isCompanyManager() && LogisticsAccess::canAccessCenter($actor,$center->id)))->pluck('id');
                $q->whereIn('recipient_role',[EscrowRemittance::ROLE_ORIGIN_HUB,EscrowRemittance::ROLE_DESTINATION_HUB])->whereIn('logistics_center_id',$centers);
            }else{
                $rider=$actor->rider;
                $q->where(fn($own)=>$own->where('recipient_user_id',$actor->id)->when($rider,fn($own)=>$own->orWhere('rider_id',$rider->id)));
            }
            $orders=$q->get();
        }
        return self::combine($orders,self::returns($actor));
    }
    public static function returns($actor, bool $hubOversight=false): Collection
    {
        if(!Schema::hasTable('return_allocations'))return collect();
        $q=$hubOversight?ReturnAllocation::whereIn('role',['collection_hub','seller_hub']):ReturnRemittanceInbox::query($actor,true);
        return $q->with(['settlement.order','recipientUser','rider','driver','center'])->get();
    }
    public static function combine(iterable $orders,iterable $returns): Collection
    {
        return collect($orders)->map(fn($row)=>RemittanceEntry::order($row))
            ->concat(collect($returns)->map(fn($row)=>RemittanceEntry::returned($row)))
            ->sortByDesc(fn($entry)=>sprintf('%020d:%010d:%s',($entry->remitted_at??$entry->created_at)?->getTimestamp()??0,$entry->id,$entry->source))->values();
    }
    public static function paginate(Collection $rows,int $perPage=15,string $pageName='page'): LengthAwarePaginator
    {
        $page=max(1,(int)request()->input($pageName,1));
        return (new LengthAwarePaginator($rows->forPage($page,$perPage)->values(),$rows->count(),$perPage,$page,
            ['path'=>request()->url(),'pageName'=>$pageName]))->withQueryString();
    }
    public static function totals(Collection $rows): array
    {
        return ['accepted'=>round((float)$rows->where('status','accepted')->sum('amount'),2),
            'remitted'=>round((float)$rows->where('status','remitted')->sum('amount'),2),
            'awaiting_release'=>round((float)$rows->whereIn('status',['held_in_escrow','pending_remittance'])->sum('amount'),2)];
    }
}

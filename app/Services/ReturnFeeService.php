<?php

namespace App\Services;

use App\Models\{LogisticsCenter,ReturnRequest,ReturnSettlement};
use App\Support\EscrowEarnings;
use Illuminate\Validation\ValidationException;

class ReturnFeeService
{
    public static function defaultRoute(ReturnRequest $case): array
    {
        $delivery=EscrowEarnings::deliveryForSeller($case->order,(int)$case->seller_id);
        $route=EscrowEarnings::resolveHubRoute($delivery);
        $origin=$delivery && EscrowEarnings::transferLegs($delivery)->isNotEmpty()
            ? $route['origin_center_id'] : ($delivery?->origin_center_id ?: $route['origin_center_id']);
        return ['collection_center_id'=>$route['destination_center_id'],'seller_center_id'=>$origin];
    }

    public static function originalReverseRoute(ReturnRequest $case,int $from,int $to): bool
    {
        $delivery=EscrowEarnings::deliveryForSeller($case->order,(int)$case->seller_id);
        $route=self::defaultRoute($case);
        return $delivery?->status==='delivered' && $from!==$to
            && $from===(int)$route['collection_center_id'] && $to===(int)$route['seller_center_id'];
    }

    /** Prices come only from Commission settings, never a submitted fee input. */
    public static function calculate(ReturnRequest $case,ReturnSettlement $agreement,array $data=[]): array
    {
        $rates=EscrowEarnings::rates();
        $defaults=self::defaultRoute($case);
        $fromId=$data['collection_center_id']??$agreement->collection_center_id??$defaults['collection_center_id'];
        $toId=$data['seller_center_id']??$agreement->seller_center_id??$defaults['seller_center_id'];
        if($agreement->physicalDirectRefund()){
            if(!empty($data['collection_center_id']) && (int)$data['collection_center_id']!==(int)$defaults['collection_center_id'])
                throw ValidationException::withMessages(['return'=>'Collection starts at the buyer-side hub. Choose a receiving hub for delivery back to the seller.']);
            $fromId=$defaults['collection_center_id'];
        }
        $lines=[];
        if($agreement->physicalReturn()){
            $from=LogisticsCenter::find($fromId);$to=LogisticsCenter::find($toId);
            if(!ReturnRouting::operational($from)||!ReturnRouting::operational($to)){
                throw ValidationException::withMessages(['return'=>'Choose active return hubs from active logistics companies. The original seller hub is recommended when available.']);
            }
            $add=function(string $role,float $amount,int $center)use(&$lines){
                $cents=ReturnSettlementService::cents($amount);
                if($cents>0)$lines[]=['role'=>$role,'amount_cents'=>$cents,'center_id'=>$center];
            };
            $add('collection_rider',$rates['pickup_rider_per_item']*max(1,(int)$case->quantity),$from->id);
            $add('collection_hub',$rates['origin_hub_fee'],$from->id);
            if($from->id!==$to->id){
                $add('inter_hub_driver',$rates['inter_hub_driver_fee'],$from->id);
                $add('seller_hub',$rates['destination_hub_fee'],$to->id);
            }
            $add('seller_delivery_rider',$rates['last_mile_rider_fee'],$to->id);
            if($agreement->resolution==='exchange')$add('replacement_delivery_rider',$rates['last_mile_rider_fee'],$to->id);
        }
        return ['source'=>'commission_settings','rates'=>$rates,'quantity'=>(int)$case->quantity,
            'collection_center_id'=>$fromId,'seller_center_id'=>$toId,'lines'=>$lines,
            'collection_hub_name'=>isset($from)?$from->name:null,'seller_hub_name'=>isset($to)?$to->name:null,
            'total_cents'=>array_sum(array_column($lines,'amount_cents'))];
    }

    public static function apply(ReturnRequest $case,ReturnSettlement $agreement,array $data=[]): void
    {
        $quote=self::calculate($case,$agreement,$data);
        $agreement->allocations()->delete();
        foreach($quote['lines'] as $line)$agreement->allocations()->create($line);
        $agreement->fill(['collection_center_id'=>$quote['collection_center_id'],'seller_center_id'=>$quote['seller_center_id'],
            'transport_cents'=>$quote['total_cents'],'fee_snapshot'=>$quote,'quoted_at'=>now()]);
    }
}

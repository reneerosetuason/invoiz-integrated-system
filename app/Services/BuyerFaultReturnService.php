<?php

namespace App\Services;

use App\Models\{EscrowRemittance,Order,ReturnRequest,ReturnSettlement};
use Illuminate\Validation\ValidationException;

class BuyerFaultReturnService
{
    public static function originalCosts(ReturnRequest $case,ReturnSettlement $agreement): array
    {
        $order=$case->order;$seller=(int)$case->seller_id;
        // A later external refund must never turn unreconciled COD into
        // apparently funded original escrow merely by changing its status.
        if(ReturnWorkflowService::fundingSummary($order,0)['funded']<=0)
            throw ValidationException::withMessages(['return'=>'Reconcile the original collected payment before proposing original cost recovery and a direct seller refund.']);
        $roles=['admin','pickup_rider','origin_hub','inter_hub_driver','destination_hub','last_mile_rider'];
        $rows=EscrowRemittance::where('order_id',$order->id)->where('seller_id',$seller)->whereIn('recipient_role',$roles)
            ->where('status','!=','voided')->get();
        if($rows->isEmpty())throw ValidationException::withMessages(['return'=>'The original order earnings breakdown must be recorded before original costs can be recovered.']);
        $items=$order->items()->where('seller_id',$seller)->get();
        $qty=max(1,(int)$items->sum('quantity'));
        $netMerch=max(0,ReturnSettlementService::cents($order->sellerSubtotal($seller)-$order->voucherDiscountForSeller($seller)));
        $previous=ReturnSettlement::where('order_id',$order->id)->whereKeyNot($agreement->id)
            ->where('workflow_policy','buyer_fault_direct')->where('status','!=','cancelled')->whereNotNull('buyer_accepted_at')
            ->whereHas('returnRequest',fn($q)=>$q->where('seller_id',$seller))->get();
        $shares=[];$original=[];
        foreach($roles as $role){
            $total=ReturnSettlementService::cents($rows->where('recipient_role',$role)->sum('amount'));
            $used=(int)$previous->sum(fn($p)=>$p->cost_snapshot['shares_cents'][$role]??0);
            $share=$role==='admin'
                ? ($netMerch>0?(int)round($total*$agreement->maximum_refund_cents/$netMerch):0)
                : (int)round($total*$case->quantity/$qty);
            $original[$role]=$total;$shares[$role]=max(0,min($share,$total-$used));
        }
        $buyerShipping=max(0,ReturnSettlementService::cents(Order::SHIPPING_FEE_PER_SELLER-$order->shippingWaivedForSeller($seller)));
        $shippingUsed=(int)$previous->sum(fn($p)=>$p->cost_snapshot['buyer_shipping_credit_cents']??0);
        $credit=max(0,min((int)round($buyerShipping*$case->quantity/$qty),$buyerShipping-$shippingUsed,
            array_sum($shares)-$shares['admin']));
        return ['source'=>'recorded_original_remittances','original_cents'=>$original,'shares_cents'=>$shares,
            'returned_quantity'=>(int)$case->quantity,'original_quantity'=>$qty,'buyer_shipping_credit_cents'=>$credit,
            'original_allocated_costs_cents'=>array_sum($shares),'seller_funded_costs_cents'=>array_sum($shares)-$credit];
    }

    public static function applyDeductions(ReturnRequest $case,ReturnSettlement $s): void
    {
        $snapshot=self::originalCosts($case,$s);
        $comp=$snapshot['seller_funded_costs_cents'];
        $gross=(int)$s->merchandise_cents+(int)$s->shipping_cents;
        $net=$gross-$comp-(int)$s->transport_cents;
        if($net<=0)throw ValidationException::withMessages(['return'=>'Original seller costs and return delivery consume this refund. Agree to another remedy instead of creating a negative refund.']);
        $s->fill(['cost_snapshot'=>$snapshot,'compensation_cents'=>$comp,'net_refund_cents'=>$net,
            'deduction_note'=>'Original seller-funded costs of PHP '.number_format($comp/100,2).
                ' and new return delivery of PHP '.number_format($s->transport_cents/100,2).
                ' are deducted from the item refund. Seller refunds PHP '.number_format($net/100,2).
                ' directly to the buyer and deposits only PHP '.number_format($s->transport_cents/100,2).' for return deliveries.']);
    }

    /** Receipt submission authorizes collection; it never claims verified cash. */
    public static function activateIfReady(Order $order,ReturnRequest $case,ReturnSettlement $s): void
    {
        if(!$s->physicalDirectRefund() || !$s->agreed() || $s->collection_authorized_at || $s->closed_at
            || ($s->net_refund_cents>0 && !$s->destination_verified_at))return;
        $submitted=$s->payments()->where('purpose','return_budget')->where('payer_user_id',$case->seller_id)
            ->whereIn('status',['submitted','verified'])->sum('amount_cents');
        if($submitted<$s->budgetCents())return;
        $committed=(int)ReturnRequest::where('order_item_id',$case->order_item_id)->whereKeyNot($case->id)
            ->whereIn('status',['approved','item_received','refunded'])->sum('quantity');
        if(!$case->item || $case->item->seller_id!=$case->seller_id || $committed+$case->quantity>$case->item->quantity)
            throw ValidationException::withMessages(['return'=>'Another return already commits this purchased quantity.']);
        $s->fill(['collection_authorized_at'=>now(),'status'=>'collection_ready'])->save();
        $case->update(['status'=>'approved','refund_amount'=>$s->net_refund_cents/100]);
        $flow=ReturnWorkflowService::start($case);
        $flow->update(['center_id'=>$s->collection_center_id]);
    }

    public static function externalRefundTotal(Order $order,?int $sellerId=null): float
    {
        return (float)ReturnRequest::where('order_id',$order->id)->when($sellerId,fn($q)=>$q->where('seller_id',$sellerId))
            ->whereHas('settlement',fn($q)=>$q->whereIn('workflow_policy',['buyer_fault_direct','seller_fault_direct','seller_direct_keep_item']))
            ->whereHas('workflow',fn($q)=>$q->where('refund_source','seller_direct')->whereNotNull('buyer_confirmed_at'))
            ->sum('refund_amount');
    }
}

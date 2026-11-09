<?php

namespace App\Services;

use App\Models\{Order,ReturnRequest,ReturnSettlement};

class SellerFaultReturnService
{
    /** Return only this seller's buyer-paid shipping, apportioned to the returned quantity. */
    public static function shippingCents(Order $order,ReturnRequest $case,ReturnSettlement $agreement): int
    {
        $seller=(int)$case->seller_id;
        $paid=ReturnSettlementService::cents(min(max(0,(float)$order->shipping_fee),
            max(0,Order::SHIPPING_FEE_PER_SELLER-$order->shippingWaivedForSeller($seller))));
        $quantity=max(1,(int)$order->items()->where('seller_id',$seller)->sum('quantity'));
        $previous=ReturnSettlement::where('order_id',$order->id)->whereKeyNot($agreement->id)
            ->where('status','!=','cancelled')->whereNotNull('buyer_accepted_at')
            ->whereHas('returnRequest',fn($q)=>$q->where('seller_id',$seller))->get();
        $remaining=max(0,$paid-(int)$previous->sum('shipping_cents'));
        $returned=(int)$previous->sum(fn($s)=>$s->returnRequest->quantity);
        return min($remaining,$returned+$case->quantity>=$quantity ? $remaining : (int)round($paid*$case->quantity/$quantity));
    }
}

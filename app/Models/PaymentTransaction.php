<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'order_id',
        'transaction_ref',
        'gateway',
        'amount',
        'status',
        'seller_earning',
        'platform_commission',
        'rider_fee',
        'logistics_fee',
        'origin_hub_fee',
        'pickup_rider_fee',
        'inter_hub_driver_fee',
        'destination_hub_fee',
        'last_mile_rider_fee',
        'seller_deficit',
        'refunded_amount',
        'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}

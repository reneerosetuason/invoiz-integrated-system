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
        'seller_earning' => 'decimal:2',
        'platform_commission' => 'decimal:2',
        'rider_fee' => 'decimal:2',
        'logistics_fee' => 'decimal:2',
        'origin_hub_fee' => 'decimal:2',
        'pickup_rider_fee' => 'decimal:2',
        'inter_hub_driver_fee' => 'decimal:2',
        'destination_hub_fee' => 'decimal:2',
        'last_mile_rider_fee' => 'decimal:2',
        'seller_deficit' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}

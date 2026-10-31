<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_id', 'tracking_number', 'rider_id', 'logistics_center_id',
        'service_area_id', 'origin_center_id', 'destination_center_id', 'pickup_rider_id',
        'amount', 'rider_fee', 'logistics_fee', 'admin_commission',
        'origin_hub_fee', 'pickup_rider_fee', 'inter_hub_driver_fee',
        'destination_hub_fee', 'last_mile_rider_fee', 'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'rider_fee' => 'decimal:2',
            'logistics_fee' => 'decimal:2',
            'admin_commission' => 'decimal:2',
            'origin_hub_fee' => 'decimal:2',
            'pickup_rider_fee' => 'decimal:2',
            'inter_hub_driver_fee' => 'decimal:2',
            'destination_hub_fee' => 'decimal:2',
            'last_mile_rider_fee' => 'decimal:2',
        ];
    }

    public const RIDER_FEE_PER_PARCEL = 15.00;
    public const ADMIN_COMMISSION_RATE = 0.10;

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    public function logisticsCenter(): BelongsTo
    {
        return $this->belongsTo(LogisticsCenter::class);
    }

    public function serviceArea(): BelongsTo
    {
        return $this->belongsTo(ServiceArea::class);
    }
}

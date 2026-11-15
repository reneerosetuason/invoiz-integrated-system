<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedDeliveryCase extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['handover_proof_path', 'driver_proof_path', 'seller_proof_path'];
    protected $casts = ['quote' => 'array', 'events' => 'array', 'quoted_at' => 'datetime',
        'accepted_at' => 'datetime', 'funded_at' => 'datetime', 'closed_at' => 'datetime',
        'driver_accepted_at' => 'datetime', 'driver_arrived_at' => 'datetime'];

    public function delivery() { return $this->belongsTo(Delivery::class); }
    public function fees() { return $this->hasMany(FailedDeliveryFee::class); }
    public function payments() { return $this->hasMany(FailedDeliveryPayment::class); }
    public function center() { return $this->belongsTo(LogisticsCenter::class); }
    public function sellerCenter() { return $this->belongsTo(LogisticsCenter::class, 'seller_center_id'); }
    public function driver() { return $this->belongsTo(HubTransportDriver::class, 'return_driver_id'); }
    public function rider() { return $this->belongsTo(Rider::class, 'return_rider_id'); }
}

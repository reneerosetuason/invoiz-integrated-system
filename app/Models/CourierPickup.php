<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierPickup extends Model
{
    protected $fillable = [
        'order_id',
        'seller_id',
        'courier',
        'pickup_at',
        'tracking_number',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'pickup_at' => 'datetime',
            'status' => 'string',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}

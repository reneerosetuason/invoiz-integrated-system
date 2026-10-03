<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $fillable = [
        'order_id',
        'rider_id',
        'status',
        'assigned_at',
        'picked_up_at',
        'delivered_at',
        'delivery_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
            'assigned_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
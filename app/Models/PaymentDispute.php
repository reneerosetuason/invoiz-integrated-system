<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentDispute extends Model
{
    protected $fillable = [
        'order_id',
        'buyer_id',
        'seller_id',
        'amount',
        'reason',
        'status',
        'resolution_note',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }
}

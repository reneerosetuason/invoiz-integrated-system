<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Remittance extends Model
{
    protected $fillable = [
        'order_id',
        'delivery_id',
        'rider_id',
        'seller_id',
        'amount',
        'status',
        'reference',
        'note',
        'sent_at',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'sent_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }

    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }

    public function sellerUser()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function scopeForSeller($query, $sellerId)
    {
        return $query->where('seller_id', $sellerId);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending', 'sent']);
    }
}

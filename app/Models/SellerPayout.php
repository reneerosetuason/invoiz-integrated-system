<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerPayout extends Model
{
    protected $fillable = [
        'seller_id',
        'amount',
        'status',
        'method',
        'reference',
        'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }
}

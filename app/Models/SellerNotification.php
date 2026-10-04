<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerNotification extends Model
{
    protected $fillable = [
        'seller_id',
        'order_id',
        'product_id',
        'type',
        'title',
        'body',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'order_id' => 'integer',
            'product_id' => 'integer',
        ];
    }

    public function scopeForSeller($query, $sellerId)
    {
        return $query->where('seller_id', $sellerId);
    }
}

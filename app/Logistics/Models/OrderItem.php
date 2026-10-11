<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Read-only mapping of the shared `order_items` table (seller attribution).
 *
 * Used by the rider conversation controller to identify the seller(s) behind
 * a delivery. Additive only; does not modify Buyer/Seller/Order systems.
 */
class OrderItem extends Model
{
    use HasFactory;

    protected $table = 'order_items';

    protected $fillable = [
        'order_id',
        'product_id',
        'seller_id',
        'quantity',
        'price',
        'variant_label',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
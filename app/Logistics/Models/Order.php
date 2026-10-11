<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Read-only mapping of the shared `orders` table.
 *
 * The logistics database reuses the application's `orders` table to link a
 * delivery to the originating buyer order. This model only exposes the shared
 * columns needed for rider conversation context; it adds no behavior and does
 * not modify the Buyer/Seller/Order systems.
 */
class Order extends Model
{
    use HasFactory;

    protected $table = 'orders';

    protected $fillable = [
        'buyer_id',
        'address_id',
        'total_amount',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }
}
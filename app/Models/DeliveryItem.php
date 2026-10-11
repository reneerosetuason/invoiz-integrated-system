<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_id',
        'name',
        'variant_label',
        'quantity',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function getSubtotalAttribute(): float
    {
        return (float) $this->price * (int) $this->quantity;
    }
}
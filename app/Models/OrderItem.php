<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        // Unified: real invoizdb cols + admin cols.
        'order_id',
        'product_id',
        'product_variant_id',
        'variant_id',
        'seller_id',
        'product_name',
        'variant_label',
        'quantity',
        'price',
        'unit_price',
        'subtotal',
        'total_price',
    ];

    protected static function booted(): void
    {
        // Keep buyer cols and admin cols in sync so every screen shows the
        // same line totals. NOTE: `subtotal` is a DB-generated column on
        // shared invoizdb — never write it, only read it.
        static::saving(function (OrderItem $it) {
            if ($it->unit_price === null && $it->price !== null) $it->unit_price = $it->price;
            if ($it->price === null && $it->unit_price !== null) $it->price = $it->unit_price;
            $line = ((float) ($it->unit_price ?? $it->price ?? 0)) * ((int) ($it->quantity ?? 0));
            if ($it->total_price === null) $it->total_price = $line;
            unset($it->subtotal);
        });
    }

    /** Line total regardless of which column pair exists. */
    public function getLineTotalAttribute(): float
    {
        if ($this->total_price !== null) return (float) $this->total_price;
        if ($this->subtotal !== null) return (float) $this->subtotal;
        return ((float) ($this->unit_price ?? $this->price ?? 0)) * ((int) ($this->quantity ?? 0));
    }

    public function getEffectiveUnitAttribute(): float
    {
        return (float) ($this->unit_price ?? $this->price ?? 0);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

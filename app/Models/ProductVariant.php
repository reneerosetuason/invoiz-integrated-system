<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'sku',
        'attributes',
        'price',
        'price_adjustment',
        'stock',
        'reserved',
        'weight',
        'image',
        'status',
        'variant_type',
        'variant_value',
    ];

    protected static function booted(): void
    {
        // Shared invoizdb requires variant_type/variant_value (NOT NULL).
        static::saving(function (ProductVariant $v) {
            if (empty($v->variant_type)) {
                $v->variant_type = 'Default';
            }
            if (empty($v->variant_value)) {
                $v->variant_value = 'Default';
            }
            if ($v->price_adjustment === null) {
                $v->price_adjustment = 0;
            }
        });
    }

    protected $casts = [
        'attributes' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

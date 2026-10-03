<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        // Unified: real invoizdb cols + admin cols. All sides stay in sync.
        'seller_id',
        'category_id',
        'name',
        'slug',
        'short_description',
        'description',
        'brand',
        'model',
        'sku',
        'material',
        'dimensions',
        'weight',
        'warranty',
        'origin',
        'status',
        'price',
        'compare_at_price',
        'cost_price',
        'stock',
        'image',
        'rating',
    ];

    protected $casts = [
        'price' => 'float',
        'compare_at_price' => 'float',
        'cost_price' => 'float',
        'stock' => 'integer',
        'rating' => 'float',
    ];

    protected static function booted(): void
    {
        static::saving(function (Product $p) {
            if (empty($p->status)) $p->status = 'active';
            if ($p->stock === null) $p->stock = 0;
            if ($p->price === null) $p->price = 0;
        });
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    /** Primary image URL: product_images (either col) -> legacy `image` col. */
    public function getPrimaryImageUrlAttribute(): ?string
    {
        try {
            $img = $this->relationLoaded('images') ? $this->images->first() : $this->images()->first();
            if ($img) {
                $path = $img->path ?? $img->image_path ?? null;
                if ($path) return url('storage/' . ltrim($path, '/'));
            }
        } catch (\Throwable $e) {}
        return $this->image ? url('storage/' . ltrim($this->image, '/')) : null;
    }

    /** Discount % from compare_at_price, for deal badges. */
    public function getDiscountPercentAttribute(): int
    {
        if ($this->compare_at_price > $this->price && $this->compare_at_price > 0) {
            return (int) round((($this->compare_at_price - $this->price) / $this->compare_at_price) * 100);
        }
        return 0;
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function getShopAttribute()
    {
        $seller = $this->relationLoaded('seller') ? $this->seller : $this->seller()->first();

        if (! $seller) {
            return null;
        }

        $initial = mb_strtoupper(mb_substr($seller->store_name, 0, 1));

        return [
            'id' => $seller->id,
            'store_name' => $seller->store_name,
            'primary_color' => $seller->primary_color,
            'accent_color' => $seller->accent_color,
            'logo' => $seller->logoUrl(),
            'initial' => $initial,
        ];
    }

    protected $appends = ['shop'];
}

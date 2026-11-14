<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $appends = ['discount_percent'];

    protected $hidden = ['cost_price', 'packaging_cost'];

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
        'packaging_cost',
        'stock',
        'low_stock_threshold',
        'image',
        'rating',
        'views_count',
        'cart_additions',
    ];

    protected $casts = [
        'price' => 'float',
        'compare_at_price' => 'float',
        'cost_price' => 'float',
        'packaging_cost' => 'float',
        'stock' => 'integer',
        'low_stock_threshold' => 'integer',
        'rating' => 'float',
    ];

    /** Numeric value of weight without unit (for input fields). */
    public function getWeightValueAttribute(): ?string
    {
        if ($this->weight === null || $this->weight === '') {
            return null;
        }
        if (preg_match('/^\s*(\d+(?:\.\d+)?)\s*(?:kg|kgs)?\s*$/i', (string) $this->weight, $m)) {
            return $m[1];
        }

        return (string) $this->weight;
    }

    /** Parsed length from dimensions string (cm). */
    public function getLengthAttribute(): ?string
    {
        return $this->parsedDimensions()['length'] ?? null;
    }

    /** Parsed width from dimensions string (cm). */
    public function getWidthAttribute(): ?string
    {
        return $this->parsedDimensions()['width'] ?? null;
    }

    /** Parsed height from dimensions string (cm). */
    public function getHeightAttribute(): ?string
    {
        return $this->parsedDimensions()['height'] ?? null;
    }

    /** Extract length, width, height from dimensions like "40 × 30 × 20 cm". */
    public function parsedDimensions(): array
    {
        if (empty($this->dimensions)) {
            return [];
        }
        if (preg_match('/^\s*(\d+(?:\.\d+)?)\s*(?:[x×*]|by)\s*(\d+(?:\.\d+)?)\s*(?:[x×*]|by)\s*(\d+(?:\.\d+)?)/iu', (string) $this->dimensions, $m)) {
            return ['length' => $m[1], 'width' => $m[2], 'height' => $m[3]];
        }

        return [];
    }

    /** Alert level for this product (seller-configurable, defaults to 5). */
    public function lowStockThreshold(): int
    {
        return max(0, (int) ($this->low_stock_threshold ?? 5));
    }

    /** True when current base stock is at or below the alert level. */
    public function isLowStock(): bool
    {
        return (int) $this->stock <= $this->lowStockThreshold();
    }

    protected static function booted(): void
    {
        static::saving(function (Product $p) {
            if (empty($p->status)) $p->status = 'active';
            if ($p->stock === null) $p->stock = 0;
            if ($p->price === null) $p->price = 0;
        });
    }

    public function scopeStorefront($query)
    {
        return $query->where('products.status', 'active')->whereHas('seller', fn ($q) => $q->approved()->whereHas('user', function ($user) {
            $user->where(fn ($active) => $active->where('account_status', 'active')->orWhere(fn ($legacy) => $legacy->where(fn ($missing) => $missing->whereNull('account_status')->orWhere('account_status', ''))->where(fn ($status) => $status->where('status', 'active')->orWhereNull('status'))));
        }));
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    /** Primary image URL: product_images (either col) -> legacy `image` col. */
    public function getPrimaryImageUrlAttribute(): ?string
    {
        $candidates = [];
        try {
            $img = $this->relationLoaded('images') ? $this->images->first() : $this->images()->first();
            if ($img) {
                $candidates[] = $img->path ?? null;
                $candidates[] = $img->image_path ?? null;
            }
        } catch (\Throwable $e) {}
        $candidates[] = $this->image;
        foreach ($candidates as $path) {
            if ($path && file_exists(storage_path('app/public/' . ltrim($path, '/')))) {
                return url('storage/' . ltrim($path, '/'));
            }
        }
        return null;
    }

    /** All valid image URLs in display order (main image first). */
    public function getGalleryUrlsAttribute(): array
    {
        $urls = [];
        $candidates = [];

        try {
            $images = $this->relationLoaded('images') ? $this->images : $this->images()->get();
            $sorted = $images->sortBy(fn ($img) => [!$img->is_main, (int) ($img->sort_order ?? 0), $img->id]);
            foreach ($sorted as $img) {
                $p = $img->resolved_path ?? $img->path ?? $img->image_path;
                if ($p) {
                    $candidates[] = $p;
                }
            }
        } catch (\Throwable $e) {}

        if (!empty($this->image) && !in_array($this->image, $candidates, true)) {
            $hasMain = false;
            try {
                $hasMain = ($this->relationLoaded('images') ? $this->images : $this->images()->get())->contains('is_main', true);
            } catch (\Throwable $e) {}

            if ($hasMain) {
                $candidates[] = $this->image;
            } else {
                array_unshift($candidates, $this->image);
            }
        }

        foreach ($candidates as $path) {
            if (!$path || !is_string($path)) continue;
            $clean = trim($path);
            if ($clean === '') continue;
            if (str_starts_with($clean, 'http://') || str_starts_with($clean, 'https://')) {
                $url = $clean;
            } else {
                $url = asset('storage/' . ltrim($clean, '/'));
            }
            if (!in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        }

        if (empty($urls) && $this->primary_image_url) {
            $urls[] = $this->primary_image_url;
        }

        return $urls;
    }

    /**
     * Analytics counters backing the admin product reports.
     * Fire-and-forget: analytics must never break shopping.
     */
    public function trackView(): void
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'views_count')) {
                $this->increment('views_count');
            }
        } catch (\Throwable $e) {}
    }

    public function trackCartAddition(): void
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'cart_additions')) {
                $this->increment('cart_additions');
            }
        } catch (\Throwable $e) {}
    }

    /** Discount % from compare_at_price, for deal badges. */
    public function getDiscountPercentAttribute(): int
    {
        $regular = \App\Support\Sales::regularPrice($this);
        $effective = (float) ($this->attributes['effective_price'] ?? $this->price);
        return \App\Support\Sales::percentOff($regular, $effective);
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id', 'user_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'product_id');
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class, 'product_id');
    }

    public function sellerNotifications()
    {
        return $this->hasMany(SellerNotification::class, 'product_id');
    }

    /** Seller catalog scope (original seller app: products.seller_id = user id). */
    public function scopeForSeller($query, $sellerId)
    {
        if ($sellerId instanceof Seller) {
            return $query->whereIn('seller_id', $sellerId->catalogIds());
        }
        return $query->where('seller_id', $sellerId);
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image) {
            return $this->primary_image_url;
        }
        return url('storage/' . ltrim($this->image, '/'));
    }

    public function getProfitPerUnitAttribute(): ?float
    {
        if ($this->cost_price === null) {
            return null;
        }
        return round((float) $this->price - (float) $this->cost_price, 2);
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
}

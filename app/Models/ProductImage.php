<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductImage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'path',
        'image_path',
        'is_main',
        'sort_order',
    ];

    protected static function booted(): void
    {
        // Real invoizdb uses `image_path`; admin uses `path`. Sync both.
        static::saving(function (ProductImage $img) {
            if (empty($img->path) && ! empty($img->image_path)) $img->path = $img->image_path;
            if (empty($img->image_path) && ! empty($img->path)) $img->image_path = $img->path;
            if ($img->sort_order === null) $img->sort_order = $img->is_main ? 0 : 1;
        });
    }

    /** Resolved path regardless of which column was filled (existing file wins). */
    public function getResolvedPathAttribute(): ?string
    {
        foreach ([$this->path ?? null, $this->image_path ?? null] as $p) {
            if ($p && file_exists(storage_path('app/public/' . ltrim($p, '/')))) {
                return $p;
            }
        }
        return $this->path ?? $this->image_path;
    }

    public function getUrlAttribute(): ?string
    {
        $p = $this->resolved_path;
        return $p ? url('storage/' . ltrim($p, '/')) : null;
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

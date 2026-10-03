<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'image',
        'status',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Real invoizdb uses `status`='active'; admin uses `active`=bool.
        // Keep them in sync so both query styles return the same rows.
        static::saving(function (Category $c) {
            if (! empty($c->status) && $c->status === 'active') $c->active = true;
            if (isset($c->active) && $c->active === false && empty($c->status)) $c->status = 'inactive';
            if (! empty($c->active) && empty($c->status)) $c->status = 'active';
            if (empty($c->slug) && ! empty($c->name)) $c->slug = strtolower(str_replace(' ', '-', $c->name));
        });
    }

    public function scopeActive($q)
    {
        return $q->where(function ($qq) {
            $qq->where('status', 'active')->orWhere('active', true);
        });
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}

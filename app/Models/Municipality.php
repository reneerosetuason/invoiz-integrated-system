<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Municipality extends Model
{
    protected $fillable = ['code', 'name', 'province_id', 'province_code', 'region_code', 'is_city'];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_code', 'code');
    }

    public function barangays(): HasMany
    {
        return $this->hasMany(Barangay::class);
    }
}
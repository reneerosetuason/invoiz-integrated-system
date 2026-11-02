<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    protected $fillable = [
        'code',
        'name',
        'region_name',
        'island_group_code',
    ];

    public function provinces(): HasMany
    {
        return $this->hasMany(Province::class, 'region_code', 'code');
    }

    public function municipalities(): HasMany
    {
        return $this->hasMany(Municipality::class, 'region_code', 'code');
    }
}

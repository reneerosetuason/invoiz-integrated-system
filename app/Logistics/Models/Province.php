<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    protected $fillable = ['code', 'name', 'region_code'];

    public function municipalities(): HasMany
    {
        return $this->hasMany(Municipality::class);
    }
}
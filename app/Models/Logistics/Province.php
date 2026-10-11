<?php

namespace App\Models\Logistics;

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
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayoutAccount extends Model
{
    protected $fillable = ['owner_type', 'owner_id', 'details'];

    protected $hidden = ['details'];

    protected function casts(): array
    {
        return ['details' => 'encrypted:array'];
    }
}

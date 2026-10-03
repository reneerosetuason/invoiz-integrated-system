<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $fillable = [
        'buyer_id',
        'recipient_name',
        'phone',
        'address_line',
        'barangay',
        'city',
        'province',
        'postal_code',
        'is_default',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }
}
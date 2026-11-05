<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GcashEscrowDeposit extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['proof_path'];

    protected $casts = ['amount' => 'decimal:2', 'receipt_verified_at' => 'datetime', 'submitted_at' => 'datetime', 'confirmed_at' => 'datetime'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}

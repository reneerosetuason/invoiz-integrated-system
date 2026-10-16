<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherClaim extends Model
{
    protected $fillable = ['buyer_id', 'voucher_id', 'order_id', 'claimed_at', 'used_at'];

    protected function casts(): array
    {
        return ['claimed_at' => 'datetime', 'used_at' => 'datetime'];
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}

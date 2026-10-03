<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderVoucher extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['order_id', 'voucher_id', 'discount_amount'];

    protected function casts(): array
    {
        return ['discount_amount' => 'decimal:2'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }
}
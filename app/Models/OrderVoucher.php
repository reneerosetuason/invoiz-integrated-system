<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class OrderVoucher extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['order_id', 'voucher_id', 'discount_amount', 'redemption_order_id'];

    protected $hidden = ['seller_user_id', 'redemption_order_id'];

    protected static function booted(): void
    {
        static::creating(function (OrderVoucher $applied) {
            if (Schema::hasColumn('order_vouchers', 'seller_user_id')) {
                $voucher = Voucher::find($applied->voucher_id);
                // Zero records platform scope; null is reserved for legacy rows
                // that have not yet received an ownership snapshot.
                $applied->seller_user_id = $voucher ? (\App\Support\Vouchers::sellerUserId($voucher) ?? 0) : 0;
            }
        });
    }

    public function voucherSellerUserId(): ?int
    {
        if ($this->getAttribute('seller_user_id') !== null) {
            return (int) $this->seller_user_id ?: null;
        }

        return $this->voucher ? \App\Support\Vouchers::sellerUserId($this->voucher) : null;
    }

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

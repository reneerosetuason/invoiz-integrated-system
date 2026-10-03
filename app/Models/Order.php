<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number',
        'seller_id',
        'buyer_id',
        'address_id',
        'status',
        'payment_status',
        'total',
        'total_amount',
        'shipping_address',
        'sub_total',
        'discount_total',
        'shipping_fee',
        'tax_total',
        'commission_amount',
        'delivery_status',
        'notes',
    ];

    protected $casts = [
        'shipping_address' => 'array',
    ];

    protected static function booted(): void
    {
        // Keep admin `total` and shared-schema `total_amount` in sync
        // so buyer checkout totals show up in admin revenue reports.
        // Guarded: only touches columns that actually exist (works both
        // before and after the correspondence migration runs).
        static::saving(function (Order $order) {
            try {
                if (empty($order->order_number) && \Illuminate\Support\Facades\Schema::hasColumn('orders', 'order_number')) {
                    $order->order_number = 'INV-' . date('ymd-His');
                }
            } catch (\Throwable $e) {}
            if (empty($order->total) && ! empty($order->total_amount)) {
                $order->total = $order->total_amount;
            }
            if (empty($order->total_amount) && ! empty($order->total)) {
                $order->total_amount = $order->total;
            }
        });
    }

    /** Revenue-safe total: prefers `total`, falls back to `total_amount`. */
    public function getEffectiveTotalAttribute(): float
    {
        return (float) ($this->total > 0 ? $this->total : $this->total_amount);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function address()
    {
        return $this->belongsTo(Address::class, 'address_id');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class, 'order_id');
    }

    public function delivery()
    {
        return $this->hasOne(Delivery::class, 'order_id');
    }

    public function statusHistories()
    {
        return $this->hasMany(OrderStatusHistory::class, 'order_id');
    }
}

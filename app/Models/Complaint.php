<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Complaint extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_id',
        'order_item_id',
        'product_id',
        'buyer_id',
        'seller_id',
        'subject',
        'description',
        'status',
        'type',
        'resolution',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * Resolves the canonical store name for admin and buyer display.
     */
    public function getStoreNameAttribute(): string
    {
        if ($this->relationLoaded('seller') && $this->seller) {
            return $this->seller->store_name ?? $this->seller->business_name ?? 'Store #' . $this->seller->id;
        }

        if ($this->seller_id) {
            $name = Seller::nameFor((int) $this->seller_id);
            if ($name && $name !== 'Store') {
                return $name;
            }
        }

        if ($this->relationLoaded('orderItem') && $this->orderItem && $this->orderItem->seller_id) {
            return Seller::nameFor((int) $this->orderItem->seller_id);
        }

        if ($this->order_item_id) {
            $item = OrderItem::find($this->order_item_id);
            if ($item && $item->seller_id) {
                return Seller::nameFor((int) $item->seller_id);
            }
        }

        if ($this->order) {
            return $this->order->store_name_display;
        }

        return 'N/A';
    }

    /**
     * Resolves the specific reported product name (if any).
     */
    public function getReportedProductNameAttribute(): ?string
    {
        if ($this->relationLoaded('orderItem') && $this->orderItem) {
            return $this->orderItem->product_name;
        }
        if ($this->order_item_id) {
            $item = OrderItem::find($this->order_item_id);
            if ($item) return $item->product_name;
        }
        if ($this->relationLoaded('product') && $this->product) {
            return $this->product->name;
        }
        if ($this->product_id) {
            $p = Product::find($this->product_id);
            if ($p) return $p->name;
        }
        return null;
    }
}

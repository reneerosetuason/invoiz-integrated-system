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
        'cancellation_reason',
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
                if (empty($order->order_number) && $order->getConnection()->getSchemaBuilder()->hasColumn('orders', 'order_number')) {
                    $order->order_number = 'INV-' . \Illuminate\Support\Str::ulid();
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

    /** Statuses from which the BUYER may still cancel (never shipped/on-the-way). */
    public const BUYER_CANCELLABLE = ['pending', 'confirmed', 'processing'];

    /**
     * Flat shipping fee per seller group (one delivery per shop).
     * The buyer pays it once per distinct seller; the rider earns it
     * once per delivery. Voucher money never touches rider pay.
     */
    public const SHIPPING_FEE_PER_SELLER = 50.0;

    /**
     * Flat logistics operations fee per delivery, funded out of the
     * seller's share so the admin cut stays exactly 10% and the rider
     * keeps the full ₱50.
     */
    public const LOGISTICS_FEE_PER_DELIVERY = 15.0;

    public function isCancellableByBuyer(): bool
    {
        return in_array($this->status, self::BUYER_CANCELLABLE, true);
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

    public function itemsForSeller($sellerId)
    {
        $ids = $sellerId instanceof Seller ? $sellerId->catalogIds() : [$sellerId];
        return $this->items()->whereIn('seller_id', $ids);
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

    /** One delivery row per seller group (each funds one rider handoff). */
    public function deliveries()
    {
        return $this->hasMany(Delivery::class, 'order_id');
    }

    public function statusHistories()
    {
        return $this->hasMany(OrderStatusHistory::class, 'order_id');
    }

    public function courierPickup()
    {
        return $this->hasOne(CourierPickup::class, 'order_id')->latestOfMany();
    }

    public function sellerNotifications()
    {
        return $this->hasMany(SellerNotification::class, 'order_id');
    }

    public function orderVouchers()
    {
        return $this->hasMany(OrderVoucher::class, 'order_id');
    }

    /**
     * Total MERCHANDISE voucher discount on this order (free-shipping
     * waivers live in their own rows and are counted separately, so the
     * two are never double-counted).
     */
    public function voucherDiscountTotal(): float
    {
        $vouchers = $this->relationLoaded('orderVouchers')
            ? $this->orderVouchers
            : $this->orderVouchers()->with('voucher')->get();
        $total = 0.0;
        foreach ($vouchers as $ov) {
            $type = $ov->voucher->discount_type ?? $ov->voucher->type ?? null;
            if ($type !== 'free_shipping') {
                $total += (float) $ov->discount_amount;
            }
        }
        return round($total, 2);
    }

    /** Gross merchandise subtotal across ALL items on this order. */
    public function itemsSubtotal(): float
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();
        return round((float) $items->sum(fn ($it) => $it->lineTotal ?? ((float) ($it->unit_price ?? $it->price ?? 0)) * ((int) ($it->quantity ?? 0))), 2);
    }

    /** Gross merchandise subtotal of this seller's items only. */
    public function sellerSubtotal($sellerId): float
    {
        if ($this->relationLoaded('items')) {
            $ids = $sellerId instanceof Seller ? $sellerId->catalogIds() : [(int) $sellerId];
            $items = $this->items->whereIn('seller_id', $ids)->values();
        } else {
            $items = $this->itemsForSeller($sellerId)->get();
        }
        return round((float) $items->sum(fn ($it) => $it->lineTotal ?? ((float) ($it->unit_price ?? $it->price ?? 0)) * ((int) ($it->quantity ?? 0))), 2);
    }

    /**
     * This seller's share of the order's voucher discount (the cost the
     * seller absorbs). Apportioned pro-rata by the seller's share of the
     * order subtotal and capped at the seller's subtotal, so multi-seller
     * orders split the discount fairly and single-seller orders take it all.
     */
    public function voucherDiscountForSeller($sellerId): float
    {
        $discount = $this->voucherDiscountTotal();
        if ($discount <= 0) {
            return 0.0;
        }
        $orderSubtotal = $this->itemsSubtotal();
        if ($orderSubtotal <= 0) {
            return 0.0;
        }
        $sellerSubtotal = $this->sellerSubtotal($sellerId);
        if ($sellerSubtotal <= 0) {
            return 0.0;
        }
        return round(min($sellerSubtotal, $discount * $sellerSubtotal / $orderSubtotal), 2);
    }

    /**
     * What this seller actually earns on the order: subtotal minus the
     * absorbed voucher discount, minus waived shipping for their group,
     * minus the flat logistics fee per delivery they benefited from.
     * Buyer shipping paid in full and rider pay are never part of this.
     */
    public function sellerNetForSeller($sellerId): float
    {
        $hasGroup = $this->relationLoaded('items')
            ? $this->items->whereIn('seller_id', $sellerId instanceof Seller ? $sellerId->catalogIds() : [(int) $sellerId])->isNotEmpty()
            : $this->itemsForSeller($sellerId)->exists();
        $logistics = $hasGroup ? self::LOGISTICS_FEE_PER_DELIVERY : 0.0;
        return round(max(0, $this->sellerSubtotal($sellerId) - $this->voucherDiscountForSeller($sellerId) - $this->shippingWaivedForSeller($sellerId) - $logistics), 2);
    }

    /** Logistics fee attributable to this seller on this order (per group). */
    public function logisticsFeeForSeller($sellerId): float
    {
        $hasGroup = $this->relationLoaded('items')
            ? $this->items->whereIn('seller_id', $sellerId instanceof Seller ? $sellerId->catalogIds() : [(int) $sellerId])->isNotEmpty()
            : $this->itemsForSeller($sellerId)->exists();
        return $hasGroup ? self::LOGISTICS_FEE_PER_DELIVERY : 0.0;
    }

    /** Cash the buyer must hand the rider for this seller group. */
    public function cashToCollectForSeller($sellerId): float
    {
        $hasGroup = $this->relationLoaded('items')
            ? $this->items->whereIn('seller_id', $sellerId instanceof Seller ? $sellerId->catalogIds() : [(int) $sellerId])->isNotEmpty()
            : $this->itemsForSeller($sellerId)->exists();

        if (! $hasGroup) {
            return 0.0;
        }

        return round(max(0,
            $this->sellerSubtotal($sellerId)
            - $this->voucherDiscountForSeller($sellerId)
            + self::SHIPPING_FEE_PER_SELLER
            - $this->shippingWaivedForSeller($sellerId)
        ), 2);
    }

    /** Net shipping the buyer pays on this order (after free-shipping waivers). */
    public function shippingNet(): float
    {
        return round(max(0, $this->shippingTotal() - $this->shippingWaivedTotal()), 2);
    }

    /** Distinct seller ids on this order, sorted (one delivery group each). */
    public function shippingGroupIds(): array
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get(['seller_id']);
        return $items->pluck('seller_id')->filter(fn ($v) => $v !== null && $v !== '')->unique()->map(fn ($v) => (int) $v)->sort()->values()->all();
    }

    /** Gross buyer shipping for this order: flat fee once per seller group. */
    public function shippingTotal(): float
    {
        return round(count($this->shippingGroupIds()) * self::SHIPPING_FEE_PER_SELLER, 2);
    }

    /** Shipping amount waived by free-shipping vouchers on this order. */
    public function shippingWaivedTotal(): float
    {
        $vouchers = $this->relationLoaded('orderVouchers')
            ? $this->orderVouchers
            : $this->orderVouchers()->with('voucher')->get();
        $total = 0.0;
        foreach ($vouchers as $ov) {
            $type = $ov->voucher->discount_type ?? $ov->voucher->type ?? null;
            if ($type === 'free_shipping') {
                $total += (float) $ov->discount_amount;
            }
        }
        return round($total, 2);
    }

    /**
     * Greedy, deterministic split of a waived shipping pot across seller
     * groups (sorted by seller id): each group waives up to one flat fee
     * until the pot runs out. Checkout and bookkeeping share this so the
     * numbers always agree.
     *
     * @return array sellerId => waived amount
     */
    public static function allocateShippingWaiver(array $groupIds, float $cap): array
    {
        sort($groupIds);
        $out = [];
        $remaining = max(0, $cap);
        foreach ($groupIds as $sid) {
            $w = round(min(self::SHIPPING_FEE_PER_SELLER, $remaining), 2);
            $out[(int) $sid] = $w;
            $remaining = round($remaining - $w, 2);
        }
        return $out;
    }

    /** This seller group's waived shipping (absorbed by the seller, never the rider). */
    public function shippingWaivedForSeller($sellerId): float
    {
        $waived = $this->shippingWaivedTotal();
        if ($waived <= 0) {
            return 0.0;
        }
        $alloc = self::allocateShippingWaiver($this->shippingGroupIds(), $waived);
        return round($alloc[(int) $sellerId] ?? 0, 2);
    }

    public function getFormattedTotalAttribute(): string
    {
        return '₱' . number_format((float) ($this->total_amount ?? $this->total ?? 0), 2);
    }
}

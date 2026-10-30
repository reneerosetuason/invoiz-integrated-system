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

    public function returnRequests()
    {
        return $this->hasMany(ReturnRequest::class);
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

    /** Store offers stay with their owner; platform offers share the remaining merchandise. */
    public function voucherDiscountForSeller($sellerId): float
    {
        $sid = $sellerId instanceof Seller ? (int) $sellerId->user_id : (int) $sellerId;
        $remaining = [];
        $discounts = [];
        foreach ($this->shippingGroupIds() as $groupId) {
            $remaining[$groupId] = $this->sellerSubtotal($groupId);
            $discounts[$groupId] = 0.0;
        }
        $vouchers = $this->relationLoaded('orderVouchers')
            ? $this->orderVouchers
            : $this->orderVouchers()->with('voucher')->get();
        $platform = 0.0;
        foreach ($vouchers as $ov) {
            $voucher = $ov->voucher;
            if (($voucher->discount_type ?? $voucher->type ?? null) === 'free_shipping') {
                continue;
            }
            $ownerId = $ov->voucherSellerUserId();
            if ($ownerId === null) {
                $platform += (float) $ov->discount_amount;
            } elseif (isset($remaining[$ownerId])) {
                $amount = min(max(0, (float) $ov->discount_amount), $remaining[$ownerId]);
                $discounts[$ownerId] += $amount;
                $remaining[$ownerId] -= $amount;
            }
        }
        // Allocate cents deterministically, preserving the order total even
        // when a store voucher has already exhausted one seller's subtotal.
        ksort($remaining);
        $remainingSubtotal = array_sum($remaining);
        $platform = min(max(0, $platform), $remainingSubtotal);
        foreach ($remaining as $groupId => $capacity) {
            $amount = $remainingSubtotal > 0
                ? round(min($capacity, $platform * $capacity / $remainingSubtotal), 2)
                : 0.0;
            $discounts[$groupId] += $amount;
            $platform = round($platform - $amount, 2);
            $remainingSubtotal = round($remainingSubtotal - $capacity, 2);
        }

        return round($discounts[$sid] ?? 0, 2);
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

        if (! $hasGroup) {
            return 0.0;
        }

        $sid = $sellerId instanceof Seller ? (int) $sellerId->user_id : (int) $sellerId;
        $calc = \App\Support\EscrowEarnings::calculateForSeller($this, $sid);

        return $calc['seller_earning'];
    }

    /** Logistics fee attributable to this seller on this order (per group). */
    public function logisticsFeeForSeller($sellerId): float
    {
        $hasGroup = $this->relationLoaded('items')
            ? $this->items->whereIn('seller_id', $sellerId instanceof Seller ? $sellerId->catalogIds() : [(int) $sellerId])->isNotEmpty()
            : $this->itemsForSeller($sellerId)->exists();

        if (! $hasGroup) {
            return 0.0;
        }

        $sid = $sellerId instanceof Seller ? (int) $sellerId->user_id : (int) $sellerId;
        $calc = \App\Support\EscrowEarnings::calculateForSeller($this, $sid);

        return $calc['merch_logistics_deducted'];
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

        $sid = $sellerId instanceof Seller ? (int) $sellerId->user_id : (int) $sellerId;
        $calc = \App\Support\EscrowEarnings::calculateForSeller($this, $sid);

        return $calc['buyer_total_payable'];
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
        $sid = $sellerId instanceof Seller ? (int) $sellerId->user_id : (int) $sellerId;
        $groups = $this->shippingGroupIds();
        $vouchers = $this->relationLoaded('orderVouchers')
            ? $this->orderVouchers
            : $this->orderVouchers()->with('voucher')->get();
        $waived = 0.0;
        foreach ($vouchers as $ov) {
            $voucher = $ov->voucher;
            if (($voucher->discount_type ?? $voucher->type ?? null) !== 'free_shipping') {
                continue;
            }
            $ownerId = $ov->voucherSellerUserId();
            $eligible = $ownerId === null ? $groups : array_values(array_filter($groups, fn ($id) => $id === $ownerId));
            $alloc = self::allocateShippingWaiver($eligible, (float) $ov->discount_amount);
            $waived += $alloc[$sid] ?? 0;
        }

        return round(min(self::SHIPPING_FEE_PER_SELLER, $waived), 2);
    }

    public function getFormattedTotalAttribute(): string
    {
        return '₱' . number_format((float) ($this->total_amount ?? $this->total ?? 0), 2);
    }

    /**
     * Store name(s) for display in admin and logistics tables.
     * Safely resolves across multi-seller and single-seller checkouts.
     */
    public function getStoreNameDisplayAttribute(): string
    {
        if ($this->relationLoaded('seller') && $this->seller) {
            $name = $this->seller->store_name ?? $this->seller->business_name;
            if (! empty($name)) {
                return $name;
            }
        }

        if ($this->relationLoaded('items')) {
            $names = $this->items->map(function ($it) {
                if ($it->relationLoaded('seller') && $it->seller) {
                    return $it->seller->store_name ?? $it->seller->business_name;
                }
                return Seller::nameFor($it->seller_id);
            })->filter()->unique()->values();

            if ($names->isNotEmpty()) {
                return $names->join(', ');
            }
        }

        if ($this->seller_id) {
            $s = Seller::find($this->seller_id);
            if ($s) {
                return $s->store_name ?? $s->business_name ?? 'Store #' . $s->id;
            }
        }

        $itemSellerIds = $this->items()->pluck('seller_id')->filter()->unique();
        if ($itemSellerIds->isNotEmpty()) {
            $sellers = Seller::whereIn('id', $itemSellerIds)
                ->orWhereIn('user_id', $itemSellerIds)
                ->get();
            $names = $sellers->map(fn ($s) => $s->store_name ?? $s->business_name)->filter()->unique();
            if ($names->isNotEmpty()) {
                return $names->join(', ');
            }
        }

        return 'N/A';
    }
}

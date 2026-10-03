<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'code',
        'name',
        'description',
        'type',
        'discount_type',
        'value',
        'discount_value',
        'min_spend',
        'max_discount',
        'valid_from',
        'valid_until',
        'starts_at',
        'ends_at',
        'usage_limit',
        'used_count',
        'status',
    ];

    protected $casts = [
        'value' => 'float',
        'discount_value' => 'float',
        'min_spend' => 'float',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Keep admin fields and shared-schema fields in sync:
        // type<->discount_type, value<->discount_value, status bool<->enum.
        static::saving(function (Voucher $v) {
            if (! empty($v->type) && empty($v->discount_type)) {
                $v->discount_type = $v->type;
            }
            if (! empty($v->discount_type) && empty($v->type)) {
                $v->type = $v->discount_type;
            }
            if ($v->value !== null && $v->discount_value === null) {
                $v->discount_value = $v->value;
            }
            if ($v->discount_value !== null && $v->value === null) {
                $v->value = $v->discount_value;
            }
            if (empty($v->name) && ! empty($v->code)) {
                $v->name = $v->code;
            }
            // Normalize status to the shared enum.
            if ($v->status === true || $v->status === 1 || $v->status === '1') {
                $v->status = 'active';
            } elseif ($v->status === false || $v->status === 0 || $v->status === '0') {
                $v->status = 'inactive';
            }
            if (empty($v->status)) {
                $v->status = 'active';
            }
        });
    }

    public function getStatusBoolAttribute(): bool
    {
        return $this->status === 'active' || $this->status === true || $this->status === 1;
    }

    /** Active vouchers within date window and usage limit — used by checkout. */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'active')->orWhere('status', 1)->orWhere('status', true);
        })->where(function ($q) {
            $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
        })->where(function ($q) {
            $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
        })->where(function ($q) {
            $q->whereNull('valid_from')->orWhere('valid_from', '<=', now());
        })->where(function ($q) {
            $q->whereNull('valid_until')->orWhere('valid_until', '>=', now());
        });
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function getLabelAttribute(): string
    {
        if ($this->type === 'percent') {
            return number_format($this->value, 0) . '% OFF';
        }
        return '₱' . number_format($this->value, 2) . ' OFF';
    }

    public function getIsActiveAttribute(): bool
    {
        $active = $this->status === 'active' || $this->status === true || $this->status === 1;
        if (! $active) {
            return false;
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }
        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }
        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }
        return true;
    }
}
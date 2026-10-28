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
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
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
            // Shared databases may store status as a boolean or a string enum.
            $status = strtolower((string) $v->status);
            $enabled = in_array($status, ['active', '1', 'true'], true);
            try {
                $statusType = \Illuminate\Support\Facades\Schema::getColumnType('vouchers', 'status');
                $booleanStatus = in_array($statusType, ['boolean', 'tinyint', 'integer', 'smallint'], true);
            } catch (\Throwable $e) {
                $booleanStatus = false;
            }
            if ($booleanStatus) {
                $v->status = $status === '' ? 1 : (int) $enabled;
            } else {
                $v->status = $status === '' ? 'active' : ($enabled ? 'active' : 'inactive');
            }
        });
    }

    public function getStatusBoolAttribute(): bool
    {
        return in_array($this->status, ['active', true, 1, '1'], true);
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
        })->where(function ($q) {
            $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit');
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
        $active = in_array($this->status, ['active', true, 1, '1'], true);
        if (! $active) {
            return false;
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }
        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }
        if ($this->valid_from && \Illuminate\Support\Carbon::parse($this->valid_from)->isFuture()) {
            return false;
        }
        if ($this->valid_until && \Illuminate\Support\Carbon::parse($this->valid_until)->isPast()) {
            return false;
        }
        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }
        return true;
    }
}

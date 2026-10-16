<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Seller extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'store_name',
        'business_name',
        'line_of_business',
        'seller_type',
        'tin',
        'region',
        'province',
        'city',
        'barangay',
        'postal_code',
        'address_line',
        'status',
        'approval_status',
        'rejection_reason',
        'terms_accepted',
        'primary_color',
        'accent_color',
        'logo',
        'id_image',
        'business_permit',
        'dti_certificate',
        'bir_2303',
    ];

    protected $casts = [
        'primary_color' => 'string',
        'accent_color' => 'string',
        'terms_accepted' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Keep admin `store_name` and shared-schema `business_name` in sync
        // so buyer/seller apps and admin always show the same shop name.
        static::saving(function (Seller $seller) {
            if (empty($seller->store_name) && ! empty($seller->business_name)) {
                $seller->store_name = $seller->business_name;
            }
            if (empty($seller->business_name) && ! empty($seller->store_name)) {
                $seller->business_name = $seller->store_name;
            }
        });
    }

    /**
     * Approved sellers across both schemas:
     * - shared invoizdb: approval_status = 'approved'
     * - legacy admin: status = 'approved'
     */
    public function scopeApproved($query)
    {
        return $query->where(function ($q) {
            $q->where('approval_status', 'approved')
              ->orWhere('status', 'approved');
        });
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved' || $this->status === 'approved';
    }

    /** Display name that works regardless of which column was filled. */
    public function getDisplayNameAttribute(): string
    {
        return $this->store_name ?: ($this->business_name ?: 'Store');
    }

    /**
     * Shop name for a seller user id — used by storefront routes.
     * Accepts either a sellers.id or a users.id so buyer/seller/admin
     * links always resolve to the same store.
     */
    public static function nameFor(int $id): string
    {
        $s = static::where('user_id', $id)->first() ?? static::find($id);
        if (! $s) {
            $u = User::find($id);
            if ($u) return $u->displayName() ?: 'Store';
            return 'Store';
        }
        return $s->display_name;
    }

    /** Resolve either the seller row id or seller user id to the user id used by messaging. */
    public static function userIdFor(int $id): int
    {
        $sellerByUser = static::where('user_id', $id)->first();
        if ($sellerByUser) {
            return (int) $sellerByUser->user_id;
        }

        $sellerById = static::find($id);
        return (int) ($sellerById?->user_id ?? $id);
    }

    /**
     * Both keys that can appear as `seller_id` across the shared DB:
     * - real invoizdb rows use the seller's USER id (products, order_items, follows)
     * - admin rows use the SELLERS row id (orders, vouchers, payouts)
     * Querying both keeps every function showing the same items.
     */
    public function catalogIds(): array
    {
        return array_values(array_unique([(int) $this->id, (int) $this->user_id]));
    }

    public function scopeCatalog($query, Seller $seller)
    {
        return $query->whereIn('seller_id', $seller->catalogIds());
    }

    public function logoUrl(): ?string
    {
        return $this->logo ? url('storage/' . ltrim($this->logo, '/')) : null;
    }

    public function idImageUrl(): ?string
    {
        return $this->id_image ? url('storage/' . ltrim($this->id_image, '/')) : null;
    }

    public function businessPermitUrl(): ?string
    {
        return $this->business_permit ? url('storage/' . ltrim($this->business_permit, '/')) : null;
    }

    public function dtiCertificateUrl(): ?string
    {
        return $this->dti_certificate ? url('storage/' . ltrim($this->dti_certificate, '/')) : null;
    }

    public function bir2303Url(): ?string
    {
        return $this->bir_2303 ? url('storage/' . ltrim($this->bir_2303, '/')) : null;
    }

    public function fullAddress(): string
    {
        $parts = array_filter([
            $this->address_line,
            $this->barangay ? 'Brgy. ' . $this->barangay : null,
            $this->city,
            $this->province,
            $this->region,
            $this->postal_code,
        ]);

        if (empty($parts) && $this->user) {
            $parts = array_filter([
                $this->user->address_line,
                $this->user->barangay ? 'Brgy. ' . $this->user->barangay : null,
                $this->user->city ?: $this->user->municipality,
                $this->user->province,
                $this->user->region,
                $this->user->postal_code,
            ]);
        }

        return implode(', ', $parts);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function profile()
    {
        return $this->hasOne(SellerProfile::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}

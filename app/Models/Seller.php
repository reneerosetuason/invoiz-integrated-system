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
        'status',
        'approval_status',
        'primary_color',
        'accent_color',
        'logo',
        'id_image',
        'business_permit',
    ];

    protected $casts = [
        'primary_color' => 'string',
        'accent_color' => 'string',
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
        $s = static::find($id);
        if (! $s) {
            $s = static::where('user_id', $id)->first();
        }
        if (! $s) {
            $u = User::find($id);
            if ($u) return $u->displayName() ?: 'Store';
            return 'Store';
        }
        return $s->display_name;
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

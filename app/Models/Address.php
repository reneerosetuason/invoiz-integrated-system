<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Address extends Model
{
    protected $fillable = [
        'buyer_id',
        'recipient_name',
        'phone',
        'address_line',
        'barangay',
        'city',
        'province',
        'region',
        'postal_code',
        'is_default',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /**
     * Repair older address rows so each buyer has at most one default, and
     * import a legacy single-profile address when no saved address exists.
     */
    public static function ensureForBuyer(int $buyerId): void
    {
        DB::transaction(function () use ($buyerId) {
            $buyer = User::query()->lockForUpdate()->find($buyerId);
            if (! $buyer) {
                return;
            }

            $addresses = static::query()
                ->where('buyer_id', $buyerId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $legacyPhone = \App\Rules\PhilippinePhone::normalize($buyer->phone);
            $legacyCity = $buyer->city ?: $buyer->municipality;
            $hasCompleteLegacyAddress = filled($buyer->address_line)
                && filled($buyer->barangay)
                && filled($legacyCity)
                && filled($buyer->province)
                && preg_match('/^09\d{9}$/', (string) $legacyPhone);

            if ($addresses->isEmpty() && $hasCompleteLegacyAddress) {
                $address = static::create([
                    'buyer_id' => $buyerId,
                    'recipient_name' => $buyer->displayName() ?: ($buyer->name ?: 'Buyer'),
                    'phone' => $legacyPhone,
                    'address_line' => $buyer->address_line,
                    'barangay' => $buyer->barangay,
                    'city' => $legacyCity,
                    'province' => $buyer->province,
                    'region' => $buyer->region,
                    'postal_code' => $buyer->postal_code,
                    'is_default' => true,
                ]);

                $addresses = collect([$address]);
            }

            $default = $addresses->firstWhere('is_default', true);
            if (! $default && $addresses->isNotEmpty()) {
                $default = $addresses->first();
                static::query()->whereKey($default->id)->update(['is_default' => true]);
            }

            if ($default) {
                static::query()
                    ->where('buyer_id', $buyerId)
                    ->where('id', '!=', $default->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
    }

    /** Freeze the chosen recipient details on the order when it is placed. */
    public function toShippingSnapshot(): array
    {
        return array_filter([
            'recipient_name' => $this->recipient_name,
            'phone' => $this->phone,
            'address_line' => $this->address_line,
            'barangay' => $this->barangay,
            'city' => $this->city,
            'province' => $this->province,
            'region' => $this->region,
            'postal_code' => $this->postal_code,
        ], fn ($value) => $value !== null && $value !== '');
    }
}

<?php

namespace App\Support;

use App\Models\Address;
use App\Models\Seller;

/**
 * F13 enforces structured address completeness only. Service-area matching is
 * intentionally unavailable until addresses, sellers, and service areas share
 * canonical location identifiers (for example PSGC codes or polygon
 * coordinates); matching free-text city or area names would be unsafe.
 */
class DeliveryServiceability
{
    private const REQUIRED_ADDRESS_FIELDS = [
        'address_line' => 'street address',
        'barangay' => 'barangay',
        'city' => 'city',
        'province' => 'province',
    ];

    public static function pickupAddressProblem(int $sellerId): ?string
    {
        $seller = Seller::query()->where('user_id', $sellerId)->first();

        if (! $seller) {
            return "Delivery is unavailable because seller #{$sellerId} has no pickup profile.";
        }

        $missing = self::missingFields($seller);
        if ($missing) {
            return 'Delivery is unavailable because the seller pickup address is incomplete (missing: '
                . implode(', ', $missing) . ').';
        }

        return null;
    }

    public static function dropoffAddressProblem(Address $address): ?string
    {
        $missing = self::missingFields($address);
        if ($missing) {
            return 'Delivery is unavailable because the selected drop-off address is incomplete (missing: '
                . implode(', ', $missing) . ').';
        }

        return null;
    }

    private static function missingFields(object $location): array
    {
        $missing = [];

        foreach (self::REQUIRED_ADDRESS_FIELDS as $field => $label) {
            if (blank($location->{$field} ?? null)) {
                $missing[] = $label;
            }
        }

        return $missing;
    }
}

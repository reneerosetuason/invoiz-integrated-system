<?php

namespace App\Support;

final class LogisticsVehicleCategory
{
    private const TWO_WHEEL = ['motorcycle', 'motorbike', 'scooter', 'moped'];

    private const FOUR_WHEEL = ['van', 'delivery van', 'utility vehicle', 'light delivery truck', 'light truck', 'truck', 'car', 'sedan', 'pickup', 'cargo van'];

    public static function for(?string $name): ?string
    {
        $normalized = strtolower(trim((string) $name));
        if (in_array($normalized, self::TWO_WHEEL, true)) {
            return 'two_wheel';
        }
        if (in_array($normalized, self::FOUR_WHEEL, true)) {
            return 'four_wheel';
        }

        return null;
    }
}

<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/** Pure calculation: money and allocation results are integer centavos. */
class TripPaymentCalculator
{
    public function calculate(array $truck, array $parcels, float $distance, array $rates): array
    {
        $positive = static function ($value, string $label): float {
            if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value <= 0) {
                throw ValidationException::withMessages(['trip' => "$label must be recorded and greater than zero."]);
            }

            return (float) $value;
        };
        $distance = $positive($distance, 'Road distance');
        $payload = $positive($truck['capacity_kg'] ?? null, 'Truck payload');
        $cargo = [];
        foreach (['length', 'width', 'height'] as $axis) {
            $cargo[] = $positive($truck["cargo_{$axis}_cm"] ?? null, "Truck cargo $axis");
        }
        $cargoVolume = array_product($cargo) / 1000000;
        sort($cargo);
        $divisor = $positive($rates['trip_volumetric_divisor'] ?? null, 'Volumetric divisor');
        if (! $parcels || count($parcels) > ($truck['parcel_capacity'] ?? 0)) {
            throw ValidationException::withMessages(['trip' => 'Manifest is empty or exceeds truck parcel capacity.']);
        }
        $weight = $volume = 0;
        $chargeable = [];
        foreach ($parcels as $parcel) {
            $id = (int) $parcel['id'];
            if (isset($chargeable[$id])) {
                throw ValidationException::withMessages(['trip' => 'Duplicate parcel in manifest.']);
            }
            $actual = $positive($parcel['weight'] ?? null, "Parcel #$id actual weight");
            $dimensions = [];
            foreach (['length', 'width', 'height'] as $axis) {
                $dimensions[] = $positive($parcel["{$axis}_cm"] ?? null, "Parcel #$id $axis");
            }
            $cubicCm = array_product($dimensions);
            sort($dimensions); // Axis-aligned rotation is allowed; diagonal packing is not assumed.
            foreach ($dimensions as $axis => $size) {
                if ($size > $cargo[$axis]) {
                    throw ValidationException::withMessages(['trip' => "Parcel #$id does not fit the usable cargo dimensions."]);
                }
            }
            $weight += $actual;
            $volume += $cubicCm / 1000000;
            // Integer milligrams give deterministic largest-remainder allocations.
            $chargeable[$id] = (int) round(max($actual, $cubicCm / $divisor) * 1000000);
        }
        if ($weight > $payload + 0.000000001 || $volume > $cargoVolume + 0.000000001) {
            throw ValidationException::withMessages(['trip' => 'Truck exceeds its permitted weight or cargo volume. Reduce the manifest or assign a larger truck.']);
        }
        $weightUtilization = $weight / $payload * 100;
        $volumeUtilization = $volume / $cargoVolume * 100;
        $utilization = max($weightUtilization, $volumeUtilization);
        $money = static fn ($value): int => (int) round((float) $value * 100, 0, PHP_ROUND_HALF_UP);
        $base = $money($rates['trip_base_payment']);
        $distanceThousandths = (int) round($distance * 1000);
        $distancePayment = intdiv($distanceThousandths * $money($rates['trip_rate_per_km']) + 500, 1000);
        $bonus = $utilization >= 80 ? $money($rates['trip_bonus_80']) : ($utilization >= 50 ? $money($rates['trip_bonus_50']) : 0);
        $total = $base + $distancePayment + $bonus;
        $sum = array_sum($chargeable);
        $allocations = $remainders = [];
        foreach ($chargeable as $id => $units) {
            // Inputs are bounded at the HTTP boundary; avoid float products in centavo allocation.
            $product = $total * $units;
            if (! is_int($product)) {
                throw ValidationException::withMessages(['trip' => 'Trip values exceed the supported allocation precision.']);
            }
            $allocations[$id] = intdiv($product, $sum);
            $remainders[$id] = $product % $sum;
        }
        $ids = array_keys($chargeable);
        usort($ids, fn ($a, $b) => ($remainders[$b] <=> $remainders[$a]) ?: ($a <=> $b));
        $left = $total - array_sum($allocations);
        for ($i = 0; $i < $left; $i++) {
            $allocations[$ids[$i]]++;
        }
        ksort($allocations);

        return compact('distance', 'weight', 'volume', 'payload', 'cargoVolume', 'weightUtilization', 'volumeUtilization', 'utilization', 'base', 'distancePayment', 'bonus', 'total', 'allocations', 'rates') + ['parcel_count' => count($parcels), 'truck' => $truck, 'parcels' => $parcels];
    }
}

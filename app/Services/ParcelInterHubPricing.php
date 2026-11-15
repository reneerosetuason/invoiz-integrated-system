<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/** One rounded fee per parcel, summed into one driver payout per truck trip. */
class ParcelInterHubPricing
{
    public function calculate(array $truck, array $parcels, float $distance, array $rates): array
    {
        $positive = function ($value, string $label): float {
            if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value <= 0) {
                throw ValidationException::withMessages(['trip' => $label.' must be recorded and greater than zero.']);
            }

            return (float) $value;
        };
        $distance = $positive($distance, 'Road distance');
        $payload = $positive($truck['capacity_kg'] ?? null, 'Truck payload');
        if (! $parcels || count($parcels) > ($truck['parcel_capacity'] ?? 0)) {
            throw ValidationException::withMessages(['trip' => 'Manifest is empty or exceeds truck parcel capacity.']);
        }
        $weight = 0;
        $ids = [];
        foreach ($parcels as &$parcel) {
            $id = (int) $parcel['id'];
            if (isset($ids[$id])) {
                throw ValidationException::withMessages(['trip' => 'Duplicate parcel in manifest.']);
            }
            $ids[$id] = true;
            $actual = $positive($parcel['weight'] ?? null, 'Parcel #'.$id.' actual weight');
            $weight += $actual;
            $parcel = ['id' => $id, 'weight' => $parcel['weight']];
        }
        unset($parcel);
        if ($weight > $payload + 0.000000001) {
            throw ValidationException::withMessages(['trip' => 'Truck exceeds its permitted weight. Reduce the manifest or assign a larger truck.']);
        }
        $weightUtilization = $weight / $payload * 100;
        $truck = array_intersect_key($truck, array_flip(['capacity_kg', 'parcel_capacity']));
        $quote = compact('distance', 'payload', 'weight', 'weightUtilization', 'parcels', 'truck')
            + ['parcel_count' => count($parcels), 'volume' => null, 'cargoVolume' => null, 'volumeUtilization' => null, 'utilization' => $weightUtilization];
        $money = function (string $key) use ($rates): int {
            $value = $rates[$key] ?? config('earnings.'.$key);
            if (! is_numeric($value) || ! is_finite((float) $value) || $value < 0) {
                throw ValidationException::withMessages(['trip' => 'The inter-hub rate '.$key.' must be a nonnegative amount.']);
            }
            $cents = round((float) $value * 100, 0, PHP_ROUND_HALF_UP);
            if (! is_finite($cents) || $cents > PHP_INT_MAX) {
                throw ValidationException::withMessages(['trip' => 'The inter-hub rate exceeds supported monetary precision.']);
            }

            return (int) $cents;
        };
        $handling = $money('inter_hub_handling_fee');
        $perKm = $money('inter_hub_rate_per_km');
        $perKgKm = $money('inter_hub_rate_per_kg_km');
        $rates = array_replace($rates, ['inter_hub_handling_fee' => $handling / 100,
            'inter_hub_rate_per_km' => $perKm / 100, 'inter_hub_rate_per_kg_km' => $perKgKm / 100]);
        $multiply = function (int $a, int $b): int {
            $result = $a * $b;
            if (! is_int($result)) {
                throw ValidationException::withMessages(['trip' => 'The inter-hub calculation exceeds supported monetary precision.']);
            }

            return $result;
        };
        $distanceMilli = (int) round($distance * 1000);
        $denominator = 1000000000; // kilometer thousandths times kilogram millionths.
        $allocations = $details = [];
        $base = $distancePayment = $weightPayment = $total = 0;
        foreach ($parcels as $parcel) {
            $weightMicro = (int) round((float) $parcel['weight'] * 1000000);
            $distanceNumerator = $multiply($multiply($distanceMilli, $perKm), 1000000);
            $weightNumerator = $multiply($multiply($distanceMilli, $weightMicro), $perKgKm);
            $numerator = $multiply($handling, $denominator) + $distanceNumerator + $weightNumerator;
            if (! is_int($numerator) || ! is_int($numerator + intdiv($denominator, 2))) {
                throw ValidationException::withMessages(['trip' => 'The inter-hub calculation exceeds supported monetary precision.']);
            }
            // Round once per parcel, then sum. Twenty PHP 42.07 parcels pay PHP 841.40.
            $fee = intdiv($numerator + intdiv($denominator, 2), $denominator);
            $distancePart = intdiv($distanceNumerator + intdiv($denominator, 2), $denominator);
            $weightPart = $fee - $handling - $distancePart;
            $allocations[(int) $parcel['id']] = $fee;
            $details[(int) $parcel['id']] = ['handling' => $handling, 'distance_payment' => $distancePart,
                'weight_payment' => $weightPart, 'actual_weight_kg' => (float) $parcel['weight'], 'total' => $fee];
            $base += $handling;
            $distancePayment += $distancePart;
            $weightPayment += $weightPart;
            $total += $fee;
            if (! is_int($total)) {
                throw ValidationException::withMessages(['trip' => 'The total inter-hub payment exceeds supported monetary precision.']);
            }
        }
        ksort($allocations);

        return array_replace($quote, ['pricing_version' => 3, 'rates' => $rates, 'base' => $base,
            'distancePayment' => $distancePayment, 'weightPayment' => $weightPayment, 'bonus' => 0,
            'total' => $total, 'allocations' => $allocations, 'parcel_pricing' => $details,
            'seller_budgets' => $allocations, 'platform_adjustment_cents' => 0]);
    }
}

<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Seller;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PickupHubRecommendations
{
    public const NEARBY_KM = 25;

    private ?array $localities = null;

    /** Recommendations are advisory: never change the seller's chosen hub. */
    public function forPickup(Collection $centers, ?Delivery $delivery, ?Seller $seller): array
    {
        $pickup = $this->coordinates($delivery?->sender_lat, $delivery?->sender_lng);
        $approximatePickup = $pickup === null;
        // A shipment's saved pickup address takes precedence over a store that moved.
        $address = trim((string) $delivery?->sender_address);
        if ($pickup === null) {
            $pickup = $address !== ''
                ? $this->fromAddress($address)
                : ($this->locality($seller?->city, $seller?->province)
                    ?? $this->fromAddress($seller?->fullAddress() ?? ''));
        }

        $recommendations = [];
        $active = $centers->filter(fn ($center) => $center->is_active);
        foreach ($active as $center) {
            $hub = $this->coordinates($center->latitude, $center->longitude);
            $approximate = $approximatePickup || $hub === null;
            $hub ??= $this->locality($center->city, $center->province)
                ?? $this->fromAddress(implode(', ', array_filter([$center->address, $center->city, $center->province])));
            $distance = $pickup !== null && $hub !== null ? $this->distance($pickup, $hub) : null;
            $recommendations[$center->id] = [
                'recommended' => $distance !== null && $distance <= self::NEARBY_KM,
                'distance_km' => $distance === null ? null : round($distance, 1),
                'approximate' => $approximate,
            ];
        }

        $sorted = $active->sort(function ($a, $b) use ($recommendations) {
            $left = $recommendations[$a->id];
            $right = $recommendations[$b->id];
            return ($right['recommended'] <=> $left['recommended'])
                ?: (($left['distance_km'] ?? INF) <=> ($right['distance_km'] ?? INF))
                ?: strcasecmp($a->name, $b->name);
        })->values();

        return ['centers' => $sorted, 'recommendations' => $recommendations];
    }

    private function coordinates($latitude, $longitude): ?array
    {
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }
        $lat = (float) $latitude;
        $lng = (float) $longitude;
        return abs($lat) <= 90 && abs($lng) <= 180 && ! ($lat === 0.0 && $lng === 0.0)
            ? [$lat, $lng] : null;
    }

    private function normalize(?string $name): string
    {
        $name = Str::lower(Str::ascii(trim((string) $name)));
        $name = preg_replace('/\(capital\)|\b(province of|city of|municipality of)\b|\bcity$/', '', $name);
        $name = preg_replace('/\bsta\.?\s+/', 'santa ', $name);
        $name = preg_replace('/\bsto\.?\s+/', 'santo ', $name);
        return trim(preg_replace('/[^a-z0-9]+/', ' ', $name));
    }

    private function index(): array
    {
        if ($this->localities === null) {
            $this->localities = [];
            $rows = json_decode(file_get_contents(database_path('data/ph-locality-coordinates.json')), true, flags: JSON_THROW_ON_ERROR);
            foreach ($rows as $row) {
                $this->localities[$this->provinceKey($row['province'])][$this->normalize($row['city'])][] = [$row['latitude'], $row['longitude']];
            }
        }
        return $this->localities;
    }

    private function locality(?string $city, ?string $province): ?array
    {
        $points = $this->index()[$this->provinceKey($province)][$this->normalize($city)] ?? [];
        if (! $points) {
            return null;
        }
        // Avoid arbitrarily picking one point where the reference is ambiguous.
        $first = $points[0];
        foreach ($points as $point) {
            if ($this->distance($first, $point) > 5) {
                return null;
            }
        }
        return $first;
    }

    private function fromAddress(string $address): ?array
    {
        $parts = array_map(fn ($part) => $this->normalize($part), explode(',', $address));
        foreach ($parts as $part) {
            $province = $this->provinceKey($part);
            if (! isset($this->index()[$province])) {
                continue;
            }
            $matches = [];
            foreach ($parts as $city) {
                if ($point = $this->locality($city, $province)) {
                    $matches[$city] = $point;
                }
            }
            if (count($matches) === 1) {
                return reset($matches);
            }
        }
        return null;
    }

    private function provinceKey(?string $province): string
    {
        $key = $this->normalize($province);
        return in_array($key, ['ncr', 'national capital region', 'manila'], true) ? 'metro manila' : $key;
    }

    private function distance(array $a, array $b): float
    {
        $lat = deg2rad($b[0] - $a[0]);
        $lng = deg2rad($b[1] - $a[1]);
        $h = sin($lat / 2) ** 2 + cos(deg2rad($a[0])) * cos(deg2rad($b[0])) * sin($lng / 2) ** 2;
        return 6371.0088 * 2 * asin(sqrt(min(1, max(0, $h))));
    }
}

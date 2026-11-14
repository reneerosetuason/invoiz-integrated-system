<?php

namespace App\Services;

use App\Models\LogisticsCenter;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class HubRoadDistanceService
{
    public function between(LogisticsCenter $origin, LogisticsCenter $destination): array
    {
        $addresses = array_map(fn ($hub) => $this->address($hub), [$origin, $destination]);
        $key = 'hub-road-distance:'.hash('sha256', json_encode([$addresses, config('hub-routing')]));
        $result = Cache::remember($key, now()->addDays(config('hub-routing.cache_days')), function () use ($origin, $destination, $addresses) {
            try {
                $points = [$this->locate($origin, $addresses[0]), $this->locate($destination, $addresses[1])];
                $coordinates = implode(';', array_map(fn ($p) => implode(',', $p['coordinates']), $points));
                $response = Http::acceptJson()->connectTimeout(3)->timeout(config('hub-routing.timeout'))
                    ->get(rtrim(config('hub-routing.router_url'), '/').'/route/v1/'.rawurlencode(config('hub-routing.profile')).'/'.$coordinates,
                        ['overview' => 'false', 'alternatives' => 'false', 'radiuses' => '500;500'])->throw()->json();
                $meters = data_get($response, 'routes.0.distance');
                if (($response['code'] ?? null) !== 'Ok' || ! is_numeric($meters) || ! is_finite((float) $meters) || $meters < 1 || $meters > 10000000) {
                    return ['error' => 'No valid road route was found between these hubs. Check their saved addresses.'];
                }

                return ['distance' => round($meters / 1000, 3), 'source' => collect($points)->contains('precision', 'street')
                    ? 'Automatic road route (OSRM / OpenStreetMap; street-level hub location estimate)'
                    : 'Automatic road route (OSRM / OpenStreetMap; matched hub addresses)'];
            } catch (HttpClientException $e) {
                return ['error' => 'Automatic road routing is temporarily unavailable. Retry calculation shortly.'];
            }
        });
        if (isset($result['error'])) {
            Cache::forget($key); // Never retain an outage for the successful-route cache lifetime.
            throw ValidationException::withMessages(['trip' => $result['error']]);
        }

        return $result;
    }

    private function address(LogisticsCenter $hub): string
    {
        if (! trim((string) $hub->address) || ! trim((string) $hub->city)) {
            throw ValidationException::withMessages(['trip' => 'Complete the street address and city for hub '.$hub->name.' to calculate road distance.']);
        }

        return implode(', ', array_filter([$hub->address, $hub->city, $hub->province, config('hub-routing.country')]));
    }

    private function locate(LogisticsCenter $hub, string $address): array
    {
        return Cache::remember('hub-geocode:v2:'.hash('sha256', json_encode([config('hub-routing'), $address])), now()->addDays(config('hub-routing.cache_days')), function () use ($hub, $address) {
            $streetQuery = trim(preg_replace('/^\s*\d+[a-z-]*[,\s]+/i', '', $hub->address));
            $queries = array_unique([$address, str_replace(',', ' ', $streetQuery).' '.$hub->city]);
            foreach ($queries as $query) {
                $features = Http::acceptJson()->connectTimeout(3)->timeout(config('hub-routing.timeout'))
                    ->get(config('hub-routing.geocoder_url'), ['q' => $query, 'limit' => 10])->throw()->json('features', []);
                $matches = collect($features)->filter(function ($feature) use ($hub) {
                    $p = $feature['properties'] ?? [];
                    $point = $feature['geometry']['coordinates'] ?? [];
                    $city = $p['city'] ?? $p['town'] ?? $p['municipality'] ?? '';
                    $normalize = fn ($s) => trim(preg_replace('/\s+/', ' ', str_replace(['city of ', ' city'], '', mb_strtolower($s))));
                    $street = trim((string) ($p['street'] ?? $p['name'] ?? ''));
                    $type = $p['type'] ?? '';
                    $namedPlace = filled($p['name'] ?? null) && str_contains(mb_strtolower($hub->address), mb_strtolower($p['name']));
                    $numberedHouse = filled($p['housenumber'] ?? null)
                        && preg_match('/^\s*'.preg_quote($p['housenumber'], '/').'(?:\s|,)/i', $hub->address);

                    return is_array($point) && count($point) === 2 && is_numeric($point[0]) && is_numeric($point[1])
                        && abs((float) $point[0]) <= 180 && abs((float) $point[1]) <= 90
                        && $normalize($city) === $normalize($hub->city)
                        && strtoupper($p['countrycode'] ?? '') === strtoupper(config('hub-routing.country_code'))
                        && $street !== '' && str_contains(mb_strtolower($hub->address), mb_strtolower($street))
                        && ($type === 'street' || ($type === 'house' && ($namedPlace || $numberedHouse)));
                })->values();
                $houses = $matches->where('properties.type', 'house')->values();
                if ($houses->isNotEmpty()) {
                    $matches = $houses;
                }
                if ($matches->count() === 1) {
                    return ['coordinates' => $matches[0]['geometry']['coordinates'], 'precision' => $matches[0]['properties']['type']];
                }
                // Never choose one of several valid-looking locations arbitrarily.
                if ($matches->count() > 1) {
                    break;
                }
            }
            throw ValidationException::withMessages(['trip' => 'The address for hub '.$hub->name.' could not be located unambiguously. Update its street address and city in hub settings.']);
        });
    }
}

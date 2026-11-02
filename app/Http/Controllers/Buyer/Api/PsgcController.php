<?php

namespace App\Http\Controllers\Buyer\Api;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * 100% local lookup controller for the Philippine Standard Geographic Code (PSGC).
 *
 * Uses locally seeded PSGC database tables and local PSGC reference files.
 * Makes ZERO external HTTP calls.
 */
class PsgcController extends Controller
{
    private const PATTERNS = [
        'regions',
        'regions/\d+/provinces',
        'regions/\d+/cities',
        'provinces/\d+/cities-municipalities',
        'cities-municipalities/\d+/barangays',
    ];

    public function show(string $path): JsonResponse
    {
        $path = trim($path, '/');

        $allowed = collect(self::PATTERNS)->contains(
            fn (string $pattern) => preg_match('#^'.$pattern.'$#', $path) === 1
        );

        if (! $allowed) {
            return response()->json(['message' => 'Unknown address lookup.'], 404);
        }

        $data = Cache::rememberForever('psgc_local:'.$path, function () use ($path) {
            return $this->resolvePath($path);
        });

        return response()->json(is_array($data) ? $data : []);
    }

    private function resolvePath(string $path): array
    {
        if ($path === 'regions') {
            return Region::orderBy('code')
                ->get()
                ->map(fn ($r) => [
                    'code' => (string) $r->code,
                    'name' => $r->name,
                    'regionName' => $r->region_name ?? $r->name,
                    'islandGroupCode' => $r->island_group_code ?? 'luzon',
                ])
                ->values()
                ->all();
        }

        if (preg_match('#^regions/(\d+)/provinces$#', $path, $matches)) {
            $regionCode = $matches[1];

            // NCR (130000000) does not have provinces
            if ($regionCode === '130000000') {
                return [];
            }

            return Province::where('region_code', $regionCode)
                ->orderBy('name')
                ->get()
                ->map(fn ($p) => [
                    'code' => (string) $p->code,
                    'name' => $p->name,
                    'regionCode' => (string) $p->region_code,
                ])
                ->values()
                ->all();
        }

        if (preg_match('#^regions/(\d+)/cities$#', $path, $matches)) {
            $regionCode = $matches[1];

            // Returns cities/municipalities directly under the region (e.g. NCR LGUs)
            return Municipality::where('region_code', $regionCode)
                ->where(function ($q) {
                    $q->whereNull('province_code')
                      ->orWhere('province_code', '')
                      ->orWhereNull('province_id');
                })
                ->orderBy('name')
                ->get()
                ->map(fn ($m) => [
                    'code' => (string) $m->code,
                    'name' => $m->name,
                    'regionCode' => (string) $m->region_code,
                    'isCity' => (bool) $m->is_city,
                ])
                ->values()
                ->all();
        }

        if (preg_match('#^provinces/(\d+)/cities-municipalities$#', $path, $matches)) {
            $provinceCode = $matches[1];

            return Municipality::where('province_code', $provinceCode)
                ->orderBy('name')
                ->get()
                ->map(fn ($m) => [
                    'code' => (string) $m->code,
                    'name' => $m->name,
                    'provinceCode' => (string) $m->province_code,
                    'isCity' => (bool) $m->is_city,
                ])
                ->values()
                ->all();
        }

        if (preg_match('#^cities-municipalities/(\d+)/barangays$#', $path, $matches)) {
            $cityCode = $matches[1];

            return Barangay::where('municipality_code', $cityCode)
                ->orderBy('name')
                ->get()
                ->map(fn ($b) => [
                    'code' => (string) $b->code,
                    'name' => $b->name,
                    'municipalityCode' => (string) $b->municipality_code,
                ])
                ->values()
                ->all();
        }

        return [];
    }
}

<?php

namespace App\Http\Controllers\Logistics\Api;

use App\Http\Controllers\Controller;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Http\JsonResponse;

class AddressController extends Controller
{
    /**
     * GET /api/address/regions
     */
    public function regions(): JsonResponse
    {
        $regions = Region::orderBy('code')
            ->select('id', 'code', 'name', 'region_name', 'island_group_code')
            ->get();

        return response()->json([
            'regions' => $regions,
        ]);
    }

    /**
     * GET /api/address/provinces[?region=CODE]
     */
    public function provinces(): JsonResponse
    {
        $query = Province::orderBy('name');

        if (is_string(request()->query('region', '')) && trim((string) request()->query('region', '')) !== '') {
            $query->where('region_code', trim((string) request()->query('region')));
        }

        $allRegions = Region::orderBy('code')->pluck('name', 'code')->all();
        if (empty($allRegions)) {
            $allRegions = config('logistics.psgc_regions', []);
        }

        return response()->json([
            'provinces' => (clone $query)->select('id', 'code', 'name', 'region_code')->get(),
            'regions' => $allRegions,
        ]);
    }

    /**
     * GET /api/address/provinces/{province}/municipalities
     */
    public function municipalities(Province $province): JsonResponse
    {
        return response()->json([
            'province' => ['id' => $province->id, 'name' => $province->name, 'code' => $province->code],
            'municipalities' => $province->municipalities()->orderBy('name')
                ->select('id', 'code', 'name')
                ->get(),
        ]);
    }

    /**
     * GET /api/address/municipalities/{municipality}/barangays
     */
    public function barangays(Municipality $municipality): JsonResponse
    {
        return response()->json([
            'municipality' => ['id' => $municipality->id, 'name' => $municipality->name, 'code' => $municipality->code],
            'barangays' => $municipality->barangays()->orderBy('name')
                ->select('id', 'code', 'name')
                ->get(),
        ]);
    }
}

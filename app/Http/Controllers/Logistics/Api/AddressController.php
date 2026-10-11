<?php

namespace App\Http\Controllers\Logistics\Api;

use App\Http\Controllers\Controller;
use App\Models\Municipality;
use App\Models\Province;
use Illuminate\Http\JsonResponse;

class AddressController extends Controller
{
    /**
     * GET /api/address/provinces
     */
    public function provinces(): JsonResponse
    {
        return response()->json([
            'provinces' => Province::orderBy('name')->select('id', 'code', 'name', 'region_code')->get(),
        ]);
    }

    /**
     * GET /api/address/provinces/{province}/municipalities
     */
    public function municipalities(Province $province): JsonResponse
    {
        return response()->json([
            'province' => ['id' => $province->id, 'name' => $province->name],
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
            'municipality' => ['id' => $municipality->id, 'name' => $municipality->name],
            'barangays' => $municipality->barangays()->orderBy('name')
                ->select('id', 'code', 'name')
                ->get(),
        ]);
    }
}





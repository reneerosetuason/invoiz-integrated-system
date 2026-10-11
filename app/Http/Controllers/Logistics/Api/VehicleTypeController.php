<?php

namespace App\Http\Controllers\Logistics\Api;

use App\Http\Controllers\Controller;
use App\Models\VehicleType;
use Illuminate\Http\JsonResponse;

class VehicleTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $types = VehicleType::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'label', 'capacity_kg', 'sort_order']);

        return response()->json([
            'vehicle_types' => $types->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t.name,
                'label' => $t.label,
                'capacity_kg' => (float) $t.capacity_kg,
            ])->values(),
        ]);
    }
}
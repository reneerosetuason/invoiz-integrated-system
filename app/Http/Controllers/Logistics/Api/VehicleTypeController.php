<?php

namespace App\Http\Controllers\Logistics\Api;

use App\Http\Controllers\Controller;
use App\Models\VehicleType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $appType = strtolower(trim((string) ($request->query('application_type') ?? $request->query('type'))));
        $category = in_array($appType, ['hub_transport_driver', 'four_wheel', 'truck', 'courier'], true)
            ? 'four_wheel'
            : 'two_wheel';

        $types = VehicleType::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'label', 'capacity_kg', 'sort_order'])
            ->filter(fn ($t) => $t->operationalCategory() === $category);

        return response()->json([
            'vehicle_types' => $types->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'label' => $t->label,
                'capacity_kg' => (float) $t->capacity_kg,
                'category' => $t->operationalCategory(),
            ])->values(),
        ]);
    }
}

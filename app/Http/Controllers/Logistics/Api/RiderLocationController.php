<?php

namespace App\Http\Controllers\Logistics\Api;

use App\Http\Controllers\Controller;
use App\Models\RiderLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RiderLocationController extends Controller
{
    /**
     * Record a rider GPS ping. Only accepted while the rider is online.
     */
    public function store(Request $request): JsonResponse
    {
        $rider = $request->user()->rider;

        if (! $rider->is_online) {
            return response()->json([
                'message' => 'Rider is offline. Location updates are ignored.',
            ], 200);
        }

        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'delivery_id' => [
                'sometimes',
                'integer',
                Rule::exists('deliveries', 'id')
                    ->where(fn ($query) => $query->where('rider_id', $rider->id)),
            ],
            'recorded_at' => 'sometimes|date',
        ]);

        RiderLocation::create([
            'rider_id' => $rider->id,
            'delivery_id' => $validated['delivery_id'] ?? null,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'recorded_at' => $validated['recorded_at'] ?? now(),
        ]);

        return response()->json([
            'message' => 'Location updated.',
        ], 201);
    }
}




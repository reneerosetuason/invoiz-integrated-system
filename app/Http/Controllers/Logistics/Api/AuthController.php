<?php

namespace App\Http\Controllers\Logistics\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Your account is not active. Please contact support.',
            ], 403);
        }

        if ($user->role !== 'rider') {
            return response()->json([
                'message' => 'This account is not registered as a rider.',
            ], 403);
        }

        if (! $user->rider) {
            return response()->json([
                'message' => 'Rider profile not found. Please contact support.',
            ], 403);
        }

        $token = $user->createToken('rider-app', ['rider'])->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    private function userPayload(User $user): array
    {
        $rider = $user->rider;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'rider' => $rider ? [
                'id' => $rider->id,
                'name' => $rider->name,
                'email' => $rider->email,
                'phone' => $rider->phone,
                'vehicle_type' => $rider->vehicle_type,
                'license_plate' => $rider->license_plate,
                'license_number' => $rider->license_number,
                'license_expiry' => $rider->license_expiry?->format('Y-m-d'),
                'license_status' => $rider->licenseStatus(),
                'license_expired' => $rider->licenseExpired(),
                'license_expiring_soon' => $rider->licenseExpiringSoon(),
                'vehicle_registration' => $rider->vehicle_registration,
                'company' => $rider->logisticsCenter?->name,
                'service_area' => $rider->serviceArea?->name,
                'status' => $rider->status,
                'is_online' => $rider->is_online,
                'can_go_online' => $rider->canGoOnline(),
                'online_block_reason' => $rider->assignmentBlockReason(),
                'avatar' => $rider->avatar ? url('storage/' . $rider->avatar) : null,
            ] : null,
        ];
    }
}
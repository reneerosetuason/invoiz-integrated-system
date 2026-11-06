<?php

namespace App\Http\Controllers\Logistics\Api;

use App\Http\Controllers\Controller;
use App\Models\HubTransportDriver;
use App\Models\RiderApplication;
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

        $email = strtolower(trim($validated['email']));
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        $driver = HubTransportDriver::whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $driver && $user && $user->hubTransportDriver) {
            $driver = $user->hubTransportDriver;
        }

        $userMatched = $user && $user->password && Hash::check($validated['password'], $user->password);
        $driverMatched = $driver && $driver->password && Hash::check($validated['password'], $driver->password);

        if (! $userMatched && ! $driverMatched) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Determine if account is Delivery Rider
        if ($user && $user->rider) {
            if (! $user->isActive()) {
                return response()->json([
                    'message' => 'Your account is not active. Please contact support.',
                ], 403);
            }

            if (! $user->hasApprovedRiderProfile()) {
                return response()->json([
                    'message' => 'Your rider account is not active or has not been approved. Please contact support.',
                ], 403);
            }

            $token = $user->createToken('rider-app', ['rider'])->plainTextToken;

            return response()->json([
                'message' => 'Login successful.',
                'token' => $token,
                'account_type' => 'delivery_rider',
                'role' => 'delivery_rider',
                'user' => $this->userPayload($user),
            ]);
        }

        // Determine if account is Hub Transport Driver
        if ($driver) {
            if ($user && ! $user->isActive()) {
                return response()->json([
                    'message' => 'Your account is not active. Please contact support.',
                ], 403);
            }

            if ($driver->status === 'inactive' || ($driver->company && $driver->company->status !== 'active')) {
                return response()->json([
                    'message' => 'Hub transport driver account is not active. Please contact support.',
                ], 403);
            }

            $application = $driver->effectiveApplication();
            if ($application && $application->status === 'pending') {
                return response()->json([
                    'message' => 'Your application is currently pending approval. Please wait for an administrator to review it.',
                ], 403);
            }
            if ($application && $application->status === 'rejected') {
                return response()->json([
                    'message' => 'Your application has been rejected. Please contact support.',
                ], 403);
            }

            $driver->tokens()->delete();
            $token = $driver->createToken('hub-courier-mobile', ['hub-courier'])->plainTextToken;

            $courierPayload = app(HubCourierController::class)->profilePayload($driver->fresh(['company', 'truck', 'ownedTruck']));

            return response()->json([
                'message' => 'Login successful.',
                'token' => $token,
                'account_type' => 'hub_transport_driver',
                'role' => 'hub_transport_driver',
                'user' => $this->userPayloadForDriver($driver, $user),
                'courier' => $courierPayload,
            ]);
        }

        // Logistics personnel (admin, logistics admin, company manager, staff,
        // or a provisioned center assignment). The backend decides the role;
        // the client only receives the resolved account type and facility.
        if ($user && ! $user->rider && $user->isLogisticsStaff()) {
            if (! $user->isActive()) {
                return response()->json([
                    'message' => 'Your account is not active. Please contact support.',
                ], 403);
            }

            $user->tokens()->where('name', 'logistics-mobile')->delete();
            $token = $user->createToken('logistics-mobile', ['logistics'])->plainTextToken;
            $payload = app(LogisticsScanController::class)->accountPayload($user);

            return response()->json([
                'message' => 'Login successful.',
                'token' => $token,
                'account_type' => $payload['account_type'],
                'role' => $payload['role'],
                'user' => $payload['user'],
                'logistics' => $payload['logistics'],
            ]);
        }

        // Neither rider profile nor driver record exists: check application status
        $application = RiderApplication::whereRaw('LOWER(email) = ?', [$email])->latest()->first();
        if ($application && $application->status === 'pending') {
            return response()->json([
                'message' => 'Your application is currently pending approval. Please wait for an administrator to review it.',
            ], 403);
        }
        if ($application && $application->status === 'rejected') {
            return response()->json([
                'message' => 'Your application has been rejected. Please contact support.',
            ], 403);
        }

        return response()->json([
            'message' => 'This account is not registered as a rider or hub transport driver.',
        ], 403);
    }

    public function logout(Request $request): JsonResponse
    {
        $actor = $request->user();

        if ($actor instanceof HubTransportDriver) {
            if ($actor->is_online) {
                $actor->update(['is_online' => false]);
            }
        } elseif ($actor instanceof User && $actor->rider?->is_online) {
            $actor->rider->update(['is_online' => false]);
        }

        $actor->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        $actor = $request->user();

        if ($actor instanceof HubTransportDriver) {
            $courierPayload = app(HubCourierController::class)->profilePayload($actor->fresh(['company', 'truck', 'ownedTruck']));

            return response()->json([
                'account_type' => 'hub_transport_driver',
                'role' => 'hub_transport_driver',
                'user' => $this->userPayloadForDriver($actor, $actor->user),
                'courier' => $courierPayload,
            ]);
        }

        if ($actor instanceof User && $actor->currentAccessToken()?->can('logistics')) {
            $payload = app(LogisticsScanController::class)->accountPayload($actor);

            return response()->json([
                'account_type' => $payload['account_type'],
                'role' => $payload['role'],
                'user' => $payload['user'],
                'logistics' => $payload['logistics'],
            ]);
        }

        return response()->json([
            'account_type' => 'delivery_rider',
            'role' => 'delivery_rider',
            'user' => $this->userPayload($actor),
        ]);
    }

    public function userPayloadForDriver(HubTransportDriver $driver, ?User $user = null): array
    {
        $courierPayload = app(HubCourierController::class)->profilePayload($driver->fresh(['company', 'truck', 'ownedTruck']));

        return [
            'id' => $user?->id ?? $driver->id,
            'name' => $driver->name,
            'email' => $driver->email,
            'phone' => $driver->phone,
            'role' => 'hub_transport_driver',
            'account_type' => 'hub_transport_driver',
            'rider' => null,
            'courier' => $courierPayload,
        ];
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
            'account_type' => 'delivery_rider',
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
                'company' => $rider->logisticsCenter?->company?->name,
                'hub' => $rider->logisticsCenter ? ['id' => $rider->logisticsCenter->id, 'name' => $rider->logisticsCenter->name, 'address' => $rider->logisticsCenter->address] : null,
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

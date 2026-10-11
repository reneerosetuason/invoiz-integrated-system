<?php

namespace App\Http\Controllers\Logistics\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Authenticated rider password changes.
 *
 * Dedicated controller so AuthController stays untouched. Only reachable
 * behind auth:sanctum + role:rider (see routes/api.php).
 */
class RiderPasswordController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
                'errors' => [
                    'current_password' => ['The current password is incorrect.'],
                ],
            ], 422);
        }

        $user->update(['password' => Hash::make($validated['password'])]);

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }
}






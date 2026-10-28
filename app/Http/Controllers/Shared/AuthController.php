<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;

use App\Models\User;
use App\Rules\PersonName;
use App\Support\NameNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->merge(NameNormalizer::cleanKeys($request->only(['name']), ['name']));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', new PersonName()],
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            // Seller accounts must use the validated seller application flow.
            'role' => 'sometimes|in:buyer,rider',
            'phone' => 'nullable|string|max:20',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'] ?? 'buyer',
            'account_status' => 'active',
            'phone' => $data['phone'] ?? null,
        ]);

        return response()->json(['user' => $user], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        $user = User::where('email', $credentials['email'])->first();
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (! $user->isActive()) {
            return response()->json(['message' => 'Your account is not active.'], 403);
        }

        if ($user->hasBlockedMarketplaceApproval()) {
            return response()->json(['message' => 'Your account is not approved.'], 403);
        }

        $token = $user->createToken('api-token')->plainTextToken;
        return response()->json(['token' => $token, 'user' => $user]);
    }
}

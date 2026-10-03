<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'sometimes|in:buyer,seller,rider',
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

        if (($data['role'] ?? 'buyer') === 'seller') {
            Seller::create([
                'user_id' => $user->id,
                'store_name' => $data['name'] . "'s Store",
                'business_name' => $data['name'] . "'s Store",
                'approval_status' => 'pending',
                'status' => 'pending',
                'primary_color' => '#16697A',
                'accent_color' => '#F0A202',
            ]);
        }

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

        $token = $user->createToken('api-token')->plainTextToken;
        return response()->json(['token' => $token, 'user' => $user]);
    }
}

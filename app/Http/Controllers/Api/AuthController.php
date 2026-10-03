<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'last_name'      => ['required', 'string', 'max:100'],
            'first_name'     => ['required', 'string', 'max:100'],
            'middle_initial' => ['nullable', 'string', 'max:10'],
            'sex'            => ['required', Rule::in(['male', 'female', 'other'])],
            'email'          => ['required', 'email', 'max:150', 'unique:users,email'],
            'password'       => ['required', 'string', 'min:8', 'confirmed'],
            'phone'          => ['required', 'string', 'max:30'],
            'birthday'       => ['required', 'date', 'before:today'],
            'province'       => ['nullable', 'string', 'max:100'],
            'municipality'   => ['nullable', 'string', 'max:100'],
            'barangay'       => ['nullable', 'string', 'max:100'],
            'address_line'   => ['nullable', 'string', 'max:255'],
        ]);

        // Age auto-generated from birthday.
        $age = \Carbon\Carbon::parse($validated['birthday'])->age;

        $idImagePath = null;
        if ($request->hasFile('id_image')) {
            $idImagePath = $request->file('id_image')->store('ids', 'public');
        }

        $user = User::create([
            ...$validated,
            'age'             => $age,
            'id_image'        => $idImagePath,
            'approval_status' => 'approved',
            'role'            => 'buyer',
            'status'          => 'active',
        ]);

        Cart::create(['buyer_id' => $user->id]);

        $token = $user->createToken('invoiz-app')->plainTextToken;

        return response()->json([
            'message' => 'Registered successfully.',
            'token'   => $token,
            'user'    => $user->only(['id', 'first_name', 'last_name', 'email', 'approval_status']),
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        if ($user->approval_status === 'pending') {
            return response()->json([
                'message' => 'Your account is still pending approval. Please wait for the administrator.',
            ], 403);
        }

        if ($user->approval_status === 'rejected') {
            return response()->json([
                'message' => 'Your registration was rejected. Please contact support.',
            ], 403);
        }

        if ($user->status !== 'active') {
            return response()->json(['message' => 'Your account is not active.'], 403);
        }

        $token = $user->createToken('invoiz-app')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token'   => $token,
            'user'    => array_merge(
                $user->only([
                    'id', 'first_name', 'last_name', 'middle_initial', 'email',
                    'sex', 'phone', 'birthday', 'age', 'role', 'approval_status',
                    'province', 'municipality', 'barangay', 'address_line', 'id_image',
                ]),
                ['seller' => $user->seller],
            ),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => array_merge(
                $user->only([
                    'id', 'last_name', 'first_name', 'middle_initial', 'sex',
                    'email', 'phone', 'birthday', 'age', 'role', 'approval_status',
                    'province', 'municipality', 'barangay', 'address_line', 'id_image',
                ]),
                ['seller' => $user->seller],
            ),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'last_name'      => ['sometimes', 'string', 'max:100'],
            'first_name'     => ['sometimes', 'string', 'max:100'],
            'middle_initial' => ['nullable', 'string', 'max:10'],
            'sex'            => ['sometimes', Rule::in(['male', 'female', 'other'])],
            'phone'          => ['sometimes', 'string', 'max:30'],
            'province'       => ['nullable', 'string', 'max:100'],
            'municipality'   => ['nullable', 'string', 'max:100'],
            'barangay'       => ['nullable', 'string', 'max:100'],
            'address_line'   => ['nullable', 'string', 'max:255'],
            'password'       => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if ($request->hasFile('id_image')) {
            if ($user->id_image) {
                Storage::disk('public')->delete($user->id_image);
            }
            $validated['id_image'] = $request->file('id_image')->store('ids', 'public');
        }

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user'    => $user->fresh()->only([
                'id', 'last_name', 'first_name', 'middle_initial', 'sex',
                'email', 'phone', 'birthday', 'age', 'role', 'approval_status',
                'province', 'municipality', 'barangay', 'address_line', 'id_image',
            ]),
        ]);
    }
}
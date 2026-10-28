<?php

namespace App\Http\Controllers\Buyer\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\User;
use App\Rules\PersonName;
use App\Support\NameNormalizer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        return app(MobileAuthController::class)->register($request);
    }

    protected function createBuyer(Request $request): User
    {
        $request->merge(NameNormalizer::cleanKeys(
            $request->only(['first_name', 'last_name', 'middle_initial']),
            ['first_name', 'last_name', 'middle_initial']
        ));

        $validated = $request->validate([
            'last_name' => ['required', 'string', 'max:100', new PersonName],
            'first_name' => ['required', 'string', 'max:100', new PersonName],
            'middle_initial' => ['nullable', 'string', 'max:10', new PersonName],
            'sex' => ['required', Rule::in(['male', 'female', 'other'])],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', \Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()->symbols(), 'confirmed'],
            'phone' => ['required', 'string', 'max:30'],
            'birthday' => ['required', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString()],
            'province' => ['nullable', 'string', 'max:100'],
            'municipality' => ['nullable', 'string', 'max:100'],
            'barangay' => ['nullable', 'string', 'max:100'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'id_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        // Age auto-generated from birthday.
        $age = Carbon::parse($validated['birthday'])->age;

        $idImagePath = null;
        if ($request->hasFile('id_image')) {
            $idImagePath = $request->file('id_image')->store('ids', 'private');
        }

        $user = User::create([
            ...$validated,
            'age' => $age,
            'id_image' => $idImagePath,
            'approval_status' => 'pending',
            'role' => 'buyer',
            'status' => 'active',
        ]);

        Cart::create(['buyer_id' => $user->id]);

        return $user;
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        if (! $user->email_verified_at) {
            return response()->json(['message' => 'Please verify your email before signing in.', 'verification_required' => true], 403);
        }

        // Logistics accounts have their own dedicated login page / API.
        if (in_array($user->role, ['admin', 'rider', 'staff', 'logistics_admin', 'logistics_staff'], true)) {
            return response()->json(['message' => 'Logistics accounts must use the Logistics login page.'], 403);
        }

        // A pending or rejected seller application must not revoke an
        // otherwise valid buyer account. Legacy seller accounts may still
        // carry the application state on the user row.
        $hasSellerApplication = ! $user->hasBlockedMarketplaceApproval();

        if ($user->approval_status === 'pending' && ! $hasSellerApplication) {
            return response()->json([
                'message' => 'Your account is still pending approval. Please wait for the administrator.',
            ], 403);
        }

        if ($user->approval_status === 'rejected' && ! $hasSellerApplication) {
            return response()->json([
                'message' => 'Your registration was rejected. Please contact support.',
            ], 403);
        }

        if (! $user->isActive()) {
            return response()->json(['message' => 'Your account is not active.'], 403);
        }

        $token = $user->createToken('invoiz-app')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'user' => array_merge(
                $user->only([
                    'id', 'first_name', 'last_name', 'middle_initial', 'email',
                    'sex', 'phone', 'birthday', 'age', 'role', 'approval_status',
                    'province', 'municipality', 'barangay', 'address_line', 'id_image',
                    'profile_picture', 'bio', 'created_at',
                ]),
                ['seller' => $user->seller],
            ),
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();
        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

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
                    'profile_picture', 'bio', 'created_at',
                ]),
                ['seller' => $user->seller],
            ),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->merge(NameNormalizer::cleanKeys(
            $request->only(['first_name', 'last_name', 'middle_initial']),
            ['first_name', 'last_name', 'middle_initial']
        ));

        $validated = $request->validate([
            'last_name' => ['sometimes', 'string', 'max:100', new PersonName],
            'first_name' => ['sometimes', 'string', 'max:100', new PersonName],
            'middle_initial' => ['nullable', 'string', 'max:10', new PersonName],
            'sex' => ['sometimes', Rule::in(['male', 'female', 'other'])],
            'phone' => ['sometimes', 'string', 'max:30'],
            'province' => ['nullable', 'string', 'max:100'],
            'municipality' => ['nullable', 'string', 'max:100'],
            'barangay' => ['nullable', 'string', 'max:100'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'current_password' => ['required_with:password', 'current_password:sanctum'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'profile_picture' => ['nullable', 'image', 'max:5120'],
            'id_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        if ($request->hasFile('profile_picture')) {
            $validated['profile_picture'] = $request->file('profile_picture')->store('profile-pictures', 'public');
        }

        if ($request->hasFile('id_image')) {
            if ($user->id_image) {
                Storage::disk('private')->delete($user->id_image);
            }
            $validated['id_image'] = $request->file('id_image')->store('ids', 'private');
        }

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        unset($validated['current_password']);
        $user->update($validated);
        if (! empty($validated['password'])) {
            $currentId = $user->currentAccessToken()?->id;
            $tokens = $user->tokens();
            if ($currentId) {
                $tokens->where('id', '!=', $currentId);
            }
            $tokens->delete();
        }

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user->fresh()->only([
                'id', 'last_name', 'first_name', 'middle_initial', 'sex',
                'email', 'phone', 'birthday', 'age', 'role', 'approval_status',
                'province', 'municipality', 'barangay', 'address_line', 'id_image',
                'profile_picture', 'bio', 'created_at',
            ]),
        ]);
    }
}

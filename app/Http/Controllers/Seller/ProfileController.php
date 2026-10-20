<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function index()
    {
        return view('seller.account.index');
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'middle_initial' => ['nullable', 'string', 'max:10'],
            'phone'      => ['required', 'string', 'max:30'],
            'email'      => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'sex'        => ['required', 'in:male,female,other'],
            'logo'       => ['nullable', 'image', 'max:5120'],
        ]);

        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('logos', 'public');
        }
        unset($validated['logo']);

        $user->update($validated);

        if ($logoPath && $user->seller) {
            if ($user->seller->logo) {
                Storage::disk('public')->delete($user->seller->logo);
            }
            $user->seller->update(['logo' => $logoPath]);
        }

        return back()->with('success', 'Profile updated successfully.');
    }

    public function updateVacation(Request $request)
    {
        $seller = Auth::user()->seller;
        if (! $seller) {
            return back()->withErrors(['vacation' => 'No seller profile found for this account.']);
        }

        $validated = $request->validate([
            'vacation_mode'    => ['required', 'boolean'],
            'vacation_message' => ['nullable', 'string', 'max:200'],
        ]);

        $seller->update([
            'vacation_mode'    => (bool) $validated['vacation_mode'],
            'vacation_message' => $validated['vacation_message'] ?? null,
        ]);

        return back()->with('success', $seller->vacation_mode
            ? 'Vacation mode is ON. Buyers can browse but cannot order from your store.'
            : 'Vacation mode is OFF. Your store is open for orders again.');
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password'     => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.']);
        }

        $user->update(['password' => Hash::make($validated['new_password'])]);

        return back()->with('success', 'Password updated successfully.');
    }
}
<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\LogisticsSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's dedicated Profile page: personal information and
     * profile-photo functionality (separate from the Settings page).
     */
    public function show(Request $request): View
    {
        return view('logistics.profile.show', [
            'user' => $request->user(),
            'settings' => LogisticsSetting::forUser($request->user()->id),
        ]);
    }

    /**
     * Update the authenticated user's profile photo.
     */
    public function updatePhoto(Request $request): RedirectResponse
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        LogisticsSetting::forUser($request->user()->id)->savePhoto($request->file('photo'));

        return Redirect::route('profile.show')->with('success', 'Profile photo updated successfully.');
    }

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('logistics.profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information, optionally saving a new
     * profile photo together with the changes, then return to the Profile
     * page with a success message.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());
        $user->save();

        if ($request->hasFile('photo')) {
            LogisticsSetting::forUser($user->id)->savePhoto($request->file('photo'));
        }

        return Redirect::route('profile.show')->with('success', 'Profile updated successfully.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}





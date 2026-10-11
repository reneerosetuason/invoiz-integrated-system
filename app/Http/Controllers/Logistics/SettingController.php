<?php

namespace App\Http\Controllers\Logistics;

use App\Models\LoginHistory;
use App\Models\LogisticsSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();
        $settings = LogisticsSetting::forUser($user->id);
        $loginHistory = LoginHistory::where('user_id', $user->id)
            ->latest('logged_in_at')
            ->take(10)
            ->get();
        $supportAdmin = \App\Models\User::where('role', 'admin')
            ->whereNotNull('email')
            ->orderBy('id')
            ->first(['name', 'email']);

        return view('logistics.settings.index', compact('user', 'settings', 'loginHistory', 'supportAdmin'));
    }

    public function updatePhoto(Request $request): RedirectResponse
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        LogisticsSetting::forUser(Auth::id())->savePhoto($request->file('photo'));

        return redirect()->route('settings.index')->with('success', 'Profile photo updated successfully.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($validated['current_password'], Auth::user()->password)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.']);
        }

        Auth::user()->update(['password' => $validated['password']]);

        return redirect()->route('settings.index')->with('success', 'Password changed successfully.');
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $notifications = [];
        foreach (LogisticsSetting::NOTIFICATION_KEYS as $key) {
            $notifications[$key] = $request->boolean($key);
        }

        LogisticsSetting::forUser(Auth::id())->update([
            'notifications'      => $notifications,
            'email_notifications' => $request->boolean('email_notifications'),
        ]);

        return redirect()->route('settings.index')->with('success', 'Notification preferences updated successfully.');
    }

    public function updateAppearance(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme' => 'required|in:' . implode(',', LogisticsSetting::THEMES),
        ]);

        LogisticsSetting::forUser(Auth::id())->savePreferences([
            'theme' => $validated['theme'],
        ]);

        return redirect()->route('settings.index')->with('success', 'Appearance updated successfully.');
    }

    public function updateRegion(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'timezone' => 'required|in:' . implode(',', LogisticsSetting::TIMEZONES),
        ]);

        LogisticsSetting::forUser(Auth::id())->savePreferences([
            'timezone' => $validated['timezone'],
        ]);

        return redirect()->route('settings.index')->with('success', 'Language & region updated successfully.');
    }

    /**
     * Persist sidebar behaviour. Called by the layout itself (fetch) whenever
     * the sidebar expand/collapse state changes, and by the Settings form.
     */
    public function updateNavigation(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'remember_sidebar' => 'sometimes|boolean',
            'sidebar_expanded' => 'sometimes|boolean',
        ]);

        $settings = LogisticsSetting::forUser(Auth::id());

        // Resolve the resulting remember flag first: the live expanded
        // state is only stored when remembering ends up ON, otherwise the
        // sidebar always starts collapsed.
        $remember = array_key_exists('remember_sidebar', $validated)
            ? (bool) $validated['remember_sidebar']
            : $settings->rememberSidebar();

        $input = [];
        if (array_key_exists('remember_sidebar', $validated)) {
            $input['remember_sidebar'] = $remember;
        }
        if (array_key_exists('sidebar_expanded', $validated) && $remember) {
            $input['sidebar_expanded'] = (bool) $validated['sidebar_expanded'];
        }

        $settings->savePreferences($input);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('settings.index')->with('success', 'Navigation preferences updated successfully.');
    }

    public function updateDelivery(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'max_attempts' => 'required|integer|min:1|max:3',
        ]);

        $existing = LogisticsSetting::forUser(Auth::id())->delivery ?? [];

        LogisticsSetting::forUser(Auth::id())->update([
            'delivery' => [
                'require_proof' => $request->boolean('require_proof'),
                'max_attempts' => (int) $validated['max_attempts'],
                'allow_reassignment' => $request->boolean('allow_reassignment'),
                'vehicle_capacities' => $existing['vehicle_capacities'] ?? null,
            ],
        ]);

        return redirect()->route('settings.index')->with('success', 'Delivery preferences saved.');
    }
}





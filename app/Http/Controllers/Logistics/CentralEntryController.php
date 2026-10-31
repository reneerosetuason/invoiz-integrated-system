<?php

namespace App\Http\Controllers\Logistics;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * INVOIZ logistics entry point.
 *
 * Guests are sent straight to the dedicated Logistics login page.
 * Authenticated users never pick a platform: their existing account
 * role decides the destination, server-side.
 */
class CentralEntryController extends Controller
{
    public function index(): RedirectResponse
    {
        $user = auth()->user();

        if (! $user) {
            return redirect('/logistics/login');
        }

        $destination = self::destinationFor($user);

        if ($destination === null) {
            Auth::guard('web')->logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();

            return redirect('/logistics/login')->with(
                'error',
                'This area is restricted to authorized logistics staff and riders.'
            );
        }

        return redirect($destination);
    }

    /**
     * Rider transit page: attempts the existing Rider App deep link with
     * the existing web fallback. Not a platform choice ??? riders only.
     * Protected by the existing rider.web middleware on the route.
     */
    public function riderEntry(): View
    {
        return view('logistics.rider.entry', [
            'deeplink' => config('invoiz.platforms.rider_deeplink', 'invoizrider://login'),
        ]);
    }

    /**
     * Resolve the post-login / central-entry destination for a user.
     *
     * All web roles stay in this application. A null destination means
     * the account has no supported entry point.
     */
    public static function destinationFor(User $user): ?string
    {
        // Logistics administrators, company managers, and staff go to the
        // dashboard (explicit paths avoid route-name collisions with the
        // main marketplace). Only the designated global Logistics Admin and
        // Platform Administrators are global; raw `logistics_admin` roles on
        // other accounts have no logistics scope.
        if ($user->isLogisticsStaff()) {
            return '/logistics/dashboard';
        }

        // Marketplace accounts assigned to a center (approved without a
        // role change) also belong to the logistics dashboard. This is kept
        // for accounts whose provisioning marker is the only link.
        if ($user->hasProvisionedCenterAssignment()) {
            return '/logistics/dashboard';
        }

        // Rider logins, including marketplace accounts linked as approved
        // riders, enter through the same rider transit page.
        if ($user->hasApprovedRiderProfile()) {
            return '/logistics/rider/entry';
        }

        if ($user->role === 'buyer') {
            return '/shop';
        }

        if ($user->role === 'seller') {
            // The shared home route handles seller approval and role choice.
            return '/home';
        }

        return null;
    }
}

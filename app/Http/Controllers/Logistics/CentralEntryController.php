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
     * Returns a URL (relative for internal routes, absolute for configured
     * external platforms), or null when the user should stay on the public
     * landing page (buyer/seller without a configured platform).
     */
public static function destinationFor(User $user): ?string
    {
        // Logistics admin/staff go to dashboard (explicit paths avoid
        // route-name collisions with the main marketplace).
        if ($user->isAdmin() || $user->isStaff() || $user->role === 'logistics_admin' || $user->role === 'logistics_staff') {
            return '/logistics/dashboard';
        }

        // Marketplace accounts assigned to a center (approved without a
        // role change) also belong to the logistics dashboard.
        if ($user->hasProvisionedCenterAssignment()) {
            return '/logistics/dashboard';
        }

        // Rider logins, including marketplace accounts linked as approved
        // riders, enter through the same rider transit page.
        if ($user->hasApprovedRiderProfile()) {
            return '/logistics/rider/entry';
        }

        if ($user->role === 'buyer') {
            return self::externalPlatformUrl('buyer_url');
        }

        if ($user->role === 'seller') {
            return self::externalPlatformUrl('seller_url');
        }

        return null;
    }

    /**
     * Buyer/Seller applications live outside this repository. Their URL is
     * a deployment-time integration point ??? never a fake local dashboard.
     */
    private static function externalPlatformUrl(string $key): ?string
    {
        $url = config("invoiz.platforms.{$key}");

        return is_string($url) && $url !== '' ? $url : null;
    }
}





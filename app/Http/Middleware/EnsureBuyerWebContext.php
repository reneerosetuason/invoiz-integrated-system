<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Logistics\CentralEntryController;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Prevent dedicated logistics sessions from entering legacy buyer routes. */
class EnsureBuyerWebContext
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user instanceof User && $request->session()->has('buyer.id')
            && (int) $request->session()->get('buyer.id') !== (int) $user->id) $request->session()->forget('buyer');
        if (! $user && $request->session()->has('buyer.id')) {
            $snapshot = User::find($request->session()->get('buyer.id'));
            if (! $snapshot || ! $snapshot->canUseBuyerArea()) $request->session()->forget('buyer');
        }
        if ($user && ! $user->canUseBuyerArea()) {
            $request->session()->forget(['buyer', 'active_role', 'seller_id']);
            // Public catalog inspection remains available to platform admins.
            if ($user->isAdmin() && $request->isMethodSafe()
                && $request->is('shop', 'products', 'sale', 'product/*', 'store/*')) return $next($request);
            if ($request->expectsJson()) return response()->json(['message' => 'This account does not have buyer access.'], 403);
            $destination = $user->isAdmin() ? '/admin/dashboard' : CentralEntryController::destinationFor($user);
            if (! $destination || in_array($destination, ['/shop', '/home'], true)) {
                // Those fallbacks lead back into the buyer area this account
                // cannot enter. End the ineligible session instead of looping.
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect('/login')->withErrors(['email' => 'This account does not currently have approved marketplace access.']);
            }
            return redirect($destination);
        }
        return $next($request);
    }
}

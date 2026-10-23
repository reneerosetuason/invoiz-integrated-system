<?php

namespace App\Http\Middleware;

use App\Models\Seller;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSeller
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect('/login');
        }

        if (! $user->isActive()) {
            Auth::logout();
            return redirect('/seller/login')->withErrors(['email' => 'Your account is not active.']);
        }

        // Seller approval changes the account role to "seller" while buyer
        // access remains available, so validate the approved store record.
        $seller = Seller::where('user_id', $user->id)
            ->approved()
            ->first();

        if (! $seller) {
            return redirect('/shop')->with('error', 'No approved seller store is linked to this account.');
        }

        if ($request->session()->get('active_role') !== 'seller') {
            return redirect()->route('choose');
        }

        $request->session()->put('seller_id', $seller->id);
        view()->share('seller', $seller);
        view()->share('userName', $user->displayName() ?: $user->email);
        view()->share('storeSubtitle', optional($seller->profile)->business_info ?? 'General Merchandise');

        return $next($request);
    }
}

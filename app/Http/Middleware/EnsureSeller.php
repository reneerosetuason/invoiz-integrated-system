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
            return redirect('/login')->withErrors(['email' => 'Your account is not active.']);
        }

        // Dual buyer+seller logins have role=buyer with an approved store:
        // accept any active account with an approved seller record.
        $seller = Seller::where('user_id', $user->id)
            ->approved()
            ->first();

        if (! $seller) {
            return redirect('/shop')->with('error', 'No approved seller store is linked to this account.');
        }

        $request->session()->put('seller_id', $seller->id);
        view()->share('seller', $seller);
        view()->share('userName', $user->displayName() ?: $user->email);
        view()->share('storeSubtitle', optional($seller->profile)->business_info ?? 'General Merchandise');

        return $next($request);
    }
}
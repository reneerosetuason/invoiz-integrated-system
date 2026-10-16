<?php

namespace App\Http\Controllers\Logistics\Auth;

use App\Http\Controllers\Logistics\CentralEntryController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Logistics\Delivery;
use App\Models\Logistics\Rider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('logistics.auth.login', [
            'stats' => [
                'activeRiders' => Rider::where('status', '!=', 'inactive')->count(),
                'deliveries' => Delivery::whereNull('archived_at')->count(),
                'completed' => Delivery::where('status', 'delivered')->count(),
            ],
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        cookie()->queue(cookie()->forget(Auth::guard('web')->getRecallerName()));

        $destination = CentralEntryController::destinationFor($request->user())
            ?? '/logistics/login';

        return redirect()->intended($destination);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
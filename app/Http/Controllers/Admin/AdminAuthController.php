<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuthController extends Controller
{
    public function showLogin()
    {
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            // Logistics accounts have their own dedicated login page.
            if (in_array($user->role, ['logistics_admin', 'logistics_staff'], true)) {
                Auth::logout();
                return back()->withErrors(['email' => 'Logistics accounts must use the Logistics login page.']);
            }
            if (! $user->is_admin) {
                Auth::logout();
                return back()->withErrors(['email' => 'Not authorized as admin']);
            }
            $request->session()->regenerate();
            return redirect()->intended('/admin/dashboard');
        }

        return back()->withErrors(['email' => 'Invalid credentials']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}

<?php

namespace App\Http\Controllers\Logistics\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the administrator-assisted password recovery view.
     *
     * Logistics accounts have no recovery email: passwords are reset by the
     * system administrator, so no reset link is ever emailed or requested.
     */
    public function create(): View
    {
        return view('logistics.auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        if (strcasecmp(trim($request->input('email')), User::GLOBAL_LOGISTICS_ADMIN_EMAIL) === 0) {
            throw ValidationException::withMessages([
                'email' => 'This account uses a restricted recovery process. Contact the system administrator.',
            ]);
        }

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}




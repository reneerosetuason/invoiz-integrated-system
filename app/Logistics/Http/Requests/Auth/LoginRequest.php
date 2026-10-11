<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => trim((string) $this->input('email')),
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Please enter your password.',
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // Logistics sessions are browser-session only: never create a persistent
        // "remember me" cookie, even if a `remember` input is posted.
        if (! Auth::attempt($this->only('email', 'password'), false)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'Invalid email or password.',
            ]);
        }

        $user = Auth::user();

        // admin/staff -> Logistics Center; rider -> rider messaging page.
        // Any other role has no access to this system.
        if (! in_array($user->role, ['admin', 'staff', 'rider'])) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => 'This account does not have Logistics access.',
            ]);
        }

        $statusMessage = match ($user->status) {
            'active' => null,
            'inactive' => 'Your account is currently inactive. Please contact an administrator.',
            'suspended' => 'Your account has been suspended. Please contact an administrator.',
            'pending' => 'Your account is awaiting approval.',
            default => 'Your account is currently inactive. Please contact an administrator.',
        };

        if ($statusMessage !== null) {
            Auth::guard('web')->logout();

            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => $statusMessage,
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}

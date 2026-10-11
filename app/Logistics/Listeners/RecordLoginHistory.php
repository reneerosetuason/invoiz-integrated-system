<?php

namespace App\Listeners;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Auth\Events\Login;

class RecordLoginHistory
{
    /**
     * Persist a sign-in record for real user logins only. Rider-portal
     * sessions authenticate the same User model, so every Logistics
     * sign-in is captured; non-User guards are ignored.
     */
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        LoginHistory::create([
            'user_id' => $event->user->id,
            'logged_in_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 512),
        ]);
    }
}

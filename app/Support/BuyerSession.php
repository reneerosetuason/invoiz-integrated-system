<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;

class BuyerSession
{
    /** Match the web shopping routes, including logins without the legacy snapshot. */
    public static function current(): ?array
    {
        $buyer = session('buyer');
        if (is_array($buyer) && ! empty($buyer['id'])) {
            $user = Auth::user();
            return array_merge([
                'first_name' => $user?->first_name ?: ($user?->name ?: 'Buyer'),
                'last_name' => $user?->last_name ?? '',
                'email' => $user?->email ?? '',
            ], $buyer);
        }

        $user = Auth::user();
        return $user ? [
            'id' => $user->id,
            'first_name' => $user->first_name ?: ($user->name ?: 'Buyer'),
            'last_name' => $user->last_name ?? '',
            'email' => $user->email,
        ] : null;
    }
}

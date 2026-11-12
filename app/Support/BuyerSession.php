<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use App\Models\User;

class BuyerSession
{
    /** Match the web shopping routes, including logins without the legacy snapshot. */
    public static function current(): ?array
    {
        $buyer = session('buyer');
        $user = Auth::user();
        if (! $user && is_array($buyer) && ! empty($buyer['id'])) {
            $user = User::find($buyer['id']);
        }
        if (! $user instanceof User || ! $user->canUseBuyerArea()) return null;

        // A legacy snapshot must never override the authenticated identity.
        $snapshot = is_array($buyer) && (int) ($buyer['id'] ?? 0) === (int) $user->id ? $buyer : [];
        return array_merge($snapshot, [
            'id' => $user->id,
            'first_name' => $user->first_name ?: ($user->name ?: 'Buyer'),
            'last_name' => $user->last_name ?? '',
            'email' => $user->email,
        ]);
    }
}

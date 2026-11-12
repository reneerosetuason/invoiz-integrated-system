<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Marketplace chat blocking: a blocker no longer wants to exchange
 * messages with the blocked user. Purely additive — admins are never
 * blocked (they can always reach anyone for moderation).
 */
class ChatBlock extends Model
{
    protected $fillable = ['blocker_id', 'blocked_id'];

    /**
     * True when either user has blocked the other. Always false when an
     * admin is involved so platform moderation cannot be shut out.
     */
    public static function blocksBetween(User $a, User $b): bool
    {
        if ($a->id === $b->id || $a->is_admin || $b->is_admin) {
            return false;
        }

        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('chat_blocks')) {
                return false;
            }

            return self::query()
                ->where(function ($q) use ($a, $b) {
                    $q->where('blocker_id', $a->id)->where('blocked_id', $b->id);
                })
                ->orWhere(function ($q) use ($a, $b) {
                    $q->where('blocker_id', $b->id)->where('blocked_id', $a->id);
                })
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Whether $blocker has specifically blocked $target. */
    public static function blocked(User $blocker, User $target): bool
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('chat_blocks')) {
                return false;
            }

            return self::query()
                ->where('blocker_id', $blocker->id)
                ->where('blocked_id', $target->id)
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }
}

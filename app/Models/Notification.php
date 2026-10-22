<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'title',
        'message',
        'icon',
        'priority',
        'is_read',
        'link',
        'center_id',
        'audience',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    /**
     * Limit the feed to what the given logistics account may see.
     *
     * Global administrators see everything. Center staff see global notices
     * plus notifications explicitly addressed to their center. Admin-only
     * notices (such as an unrouted pickup) never leak to center staff.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin() || $user->isLogisticsAdmin()) {
            return $query;
        }

        if (! $user->isLogisticsStaff()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $inner) use ($user): void {
            $inner->where('audience', 'global');

            if ($user->center_id) {
                $inner->orWhere(function (Builder $center) use ($user): void {
                    $center->where('audience', 'center')
                        ->where('center_id', $user->center_id);
                });
            }
        });
    }

    /**
     * Limit notifications to what the given user chose to receive: mapped
     * types whose preference key is enabled, plus unmapped operational
     * alerts (pickup in transit, cancellation) which are always shown.
     * Users without a settings row yet see everything.
     */
    public function scopeForUserPreferences(Builder $query, int $userId): Builder
    {
        $settings = LogisticsSetting::where('user_id', $userId)->first();

        if (! $settings) {
            return $query;
        }

        $allowed = [];
        $mapped = [];

        foreach (LogisticsSetting::NOTIFICATION_TYPE_MAP as $key => $types) {
            $mapped = array_merge($mapped, $types);

            if ($settings->notificationEnabled($key)) {
                $allowed = array_merge($allowed, $types);
            }
        }

        return $query->where(function (Builder $inner) use ($allowed, $mapped) {
            $inner->whereIn('type', $allowed)
                ->orWhereNotIn('type', array_values(array_unique($mapped)));
        });
    }

    public function markAsRead(): void
    {
        $this->update(['is_read' => true]);
    }
}
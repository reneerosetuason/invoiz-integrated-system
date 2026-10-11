<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class LogisticsSetting extends Model
{
    use HasFactory;

    protected $table = 'logistics_settings';

    public const NOTIFICATION_KEYS = [
        'rider_applications',
        'application_updates',
        'delivery_requests',
        'failed_deliveries',
        'failed_pickups',
        'rider_status_updates',
        'delivery_completed',
        'new_messages',
    ];

    /**
     * Maps each preference key to the in-system notification `type` values it
     * governs. Types with no entry here are operational alerts (pickup in
     * transit, cancellation) that are always shown.
     */
    public const NOTIFICATION_TYPE_MAP = [
        'rider_applications' => ['new_rider_application', 'rider_approved', 'rider_rejected'],
        'application_updates' => ['new_center_application', 'center_approved', 'center_rejected'],
        'delivery_requests' => ['new_delivery_request'],
        'failed_deliveries' => ['delivery_delivery_failed'],
        'failed_pickups' => ['pickup_failed'],
        'rider_status_updates' => ['rider_accepted_delivery', 'rider_status_changed'],
        'delivery_completed' => ['delivery_delivered'],
        'new_messages' => ['new_message'],
    ];

    public const THEMES = ['light', 'dark', 'system'];

    /**
     * Timezones the application can actually render. Dates are displayed
     * through the app timezone, so any IANA zone here takes real effect.
     */
    public const TIMEZONES = [
        'Asia/Manila',
        'Asia/Singapore',
        'Asia/Tokyo',
        'Asia/Dubai',
        'Australia/Sydney',
        'Europe/London',
        'Europe/Paris',
        'America/New_York',
        'America/Los_Angeles',
        'UTC',
    ];

    protected $fillable = [
        'user_id',
        'photo_path',
        'notifications',
        'email_notifications',
        'delivery',
        'preferences',
    ];

    protected function casts(): array
    {
        return [
            'notifications' => 'array',
            'email_notifications' => 'boolean',
            'delivery' => 'array',
            'preferences' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function forUser(int $userId): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId],
            [
                'notifications' => array_fill_keys(static::NOTIFICATION_KEYS, true),
                'email_notifications' => true,
                'delivery' => [
                    'require_proof' => true,
                    'max_attempts' => 2,
                    'allow_reassignment' => true,
                ],
            ]
        );
    }

    public function notificationEnabled(string $key): bool
    {
        // Opt-out semantics: keys missing from older rows (e.g. newly added
        // categories) default to ON so existing users keep receiving them
        // until they explicitly switch a toggle off.
        return (bool) ($this->notifications[$key] ?? true);
    }

    /**
     * Read a generic preference with a default fallback.
     */
    public function preference(string $key, $default = null)
    {
        return ($this->preferences ?? [])[$key] ?? $default;
    }

    /**
     * Persist generic preferences (theme, timezone, navigation) after
     * validating each value against the allow-lists on this model.
     *
     * @return array<int, string> Validation error messages, empty on success.
     */
    public function savePreferences(array $input): array
    {
        $errors = [];
        $preferences = $this->preferences ?? [];

        if (array_key_exists('theme', $input)) {
            if (! in_array($input['theme'], static::THEMES, true)) {
                $errors[] = 'Invalid appearance theme.';
            } else {
                $preferences['theme'] = $input['theme'];
            }
        }

        if (array_key_exists('timezone', $input)) {
            if (! in_array($input['timezone'], static::TIMEZONES, true)) {
                $errors[] = 'Unsupported timezone.';
            } else {
                $preferences['timezone'] = $input['timezone'];
            }
        }

        if (array_key_exists('remember_sidebar', $input)) {
            $preferences['remember_sidebar'] = (bool) $input['remember_sidebar'];
        }

        if (array_key_exists('sidebar_expanded', $input)) {
            $preferences['sidebar_expanded'] = (bool) $input['sidebar_expanded'];
        }

        if ($errors === []) {
            $this->update(['preferences' => $preferences]);
        }

        return $errors;
    }

    public function theme(): string
    {
        $theme = $this->preference('theme', 'light');

        return in_array($theme, static::THEMES, true) ? $theme : 'light';
    }

    public function timezone(): ?string
    {
        $timezone = $this->preference('timezone');

        return in_array($timezone, static::TIMEZONES, true) ? $timezone : null;
    }

    public function rememberSidebar(): bool
    {
        return (bool) $this->preference('remember_sidebar', false);
    }

    public function sidebarExpanded(): bool
    {
        return (bool) $this->preference('sidebar_expanded', false);
    }

    /**
     * Persist a profile photo for this settings row.
     *
     * The new file is stored first, then photo_path is updated in the
     * database, and only after both succeed is the previous file removed
     * from public/uploads/avatars. If storing the file or persisting the
     * database row fails, the previous photo is left untouched (a stray new
     * file, if any, is cleaned up) so the user never loses their photo.
     *
     * Every upload uses a fresh UUID filename, which means the rendered URL
     * changes on each upload — the browser can never serve a stale cached
     * image for a reused path.
     */
    public function savePhoto(UploadedFile $file): string
    {
        $directory = public_path('uploads/avatars');

        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $extension = strtolower($file->extension() ?: $file->getClientOriginalExtension() ?: 'jpg');
        $filename = Str::uuid() . '.' . $extension;
        $previousPath = $this->photo_path;

        $file->move($directory, $filename);

        $path = 'uploads/avatars/' . $filename;

        try {
            $this->update(['photo_path' => $path]);
        } catch (\Throwable $e) {
            @unlink($directory . DIRECTORY_SEPARATOR . $filename);

            throw $e;
        }

        if ($previousPath && $previousPath !== $path && file_exists(public_path($previousPath))) {
            @unlink(public_path($previousPath));
        }

        return $path;
    }

    public static function vehicleCapacities(): array
    {
        $fromTable = VehicleType::capacityMap();

        if (!empty($fromTable)) {
            return $fromTable;
        }

        return config('logistics.vehicle_capacities', []);
    }
}

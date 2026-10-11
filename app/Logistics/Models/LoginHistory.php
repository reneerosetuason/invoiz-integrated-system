<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginHistory extends Model
{
    protected $fillable = [
        'user_id',
        'logged_in_at',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'logged_in_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Short human description of the device/browser, derived from the stored
     * user agent without exposing the raw string.
     */
    public function deviceLabel(): string
    {
        $agent = (string) ($this->user_agent ?? '');

        if ($agent === '') {
            return 'Unknown device';
        }

        $browser = str_contains($agent, 'Edg/') ? 'Edge'
            : (str_contains($agent, 'Chrome/') ? 'Chrome'
            : (str_contains($agent, 'Firefox/') ? 'Firefox'
            : (str_contains($agent, 'Safari/') && ! str_contains($agent, 'Chrome/') ? 'Safari' : 'Browser')));

        $platform = str_contains($agent, 'Windows') ? 'Windows'
            : (str_contains($agent, 'Android') ? 'Android'
            : (str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') ? 'iOS'
            : (str_contains($agent, 'Macintosh') ? 'macOS'
            : (str_contains($agent, 'Linux') ? 'Linux' : 'Device'))));

        return $browser . ' on ' . $platform;
    }
}

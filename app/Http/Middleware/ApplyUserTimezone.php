<?php

namespace App\Http\Middleware;

use App\Models\LogisticsSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ApplyUserTimezone
{
    /**
     * Render every date/time in the authenticated user's chosen timezone.
     * Users without a saved timezone keep the application default and the
     * database session untouched (preserving the existing symmetric
     * read/write behaviour). Only users with an explicit preference get
     * their connection session aligned, so their whole request lifecycle —
     * writes, reads, and DATE() grouping — stays self-consistent in their
     * own timezone. Values always come from the allow-list, so an invalid
     * stored value can never break date rendering.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $timezone = config('app.timezone', 'UTC');
        $custom = null;

        if ($request->user()) {
            $custom = LogisticsSetting::where('user_id', $request->user()->id)
                ->first()
                ?->timezone();

            if ($custom) {
                $timezone = $custom;
            }
        }

        date_default_timezone_set($timezone);
        config(['app.timezone' => $timezone]);

        if ($custom) {
            // TIMESTAMP columns round-trip through the connection timezone,
            // so align the session with the display timezone (as a numeric
            // offset, which needs no loaded tz tables).
            try {
                $offset = (new \DateTimeZone($timezone))->getOffset(new \DateTime('now', new \DateTimeZone('UTC')));
                $sign = $offset < 0 ? '-' : '+';
                $offset = abs($offset);
                DB::statement(sprintf(
                    'SET time_zone = "%s%02d:%02d"',
                    $sign,
                    (int) ($offset / 3600),
                    (int) (($offset % 3600) / 60)
                ));
            } catch (\Throwable $e) {
                // Display timezone still applies; only DB-side conversion
                // stays at the server default.
            }
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware\Logistics;

use App\Models\Logistics\LogisticsSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ApplyUserTimezone
{
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
            }
        }

        return $next($request);
    }
}
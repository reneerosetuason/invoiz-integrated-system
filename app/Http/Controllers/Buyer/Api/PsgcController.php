<?php

namespace App\Http\Controllers\Buyer\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Read-only proxy for the public PSGC address dataset.
 *
 * The buyer app runs on phones that may only reach this server (LAN/dev
 * builds), so the cascading address dropdowns ask us instead of the public
 * API directly. Only list endpoints are exposed, and answers are cached for a
 * day.
 */
class PsgcController extends Controller
{
    private const PATTERNS = [
        'regions',
        'regions/\d+/provinces',
        'regions/\d+/cities',
        'provinces/\d+/cities-municipalities',
        'cities-municipalities/\d+/barangays',
    ];

    public function show(string $path): JsonResponse
    {
        $path = trim($path, '/');

        $allowed = collect(self::PATTERNS)->contains(
            fn (string $pattern) => preg_match('#^'.$pattern.'$#', $path) === 1
        );

        if (! $allowed) {
            return response()->json(['message' => 'Unknown address lookup.'], 404);
        }

        try {
            $data = Cache::remember('psgc:'.$path, now()->addDay(), function () use ($path) {
                $data = Http::timeout(15)
                    ->acceptJson()
                    ->get('https://psgc.gitlab.io/api/'.$path)
                    ->throw()
                    ->json();

                if (! is_array($data) || ! array_is_list($data)) {
                    throw new \UnexpectedValueException('Invalid PSGC list response.');
                }

                return $data;
            });
        } catch (ConnectionException|RequestException|\UnexpectedValueException) {
            // A failed lookup must be retryable, rather than cached as an
            // empty province list and mistaken for a province-free region.
            return response()->json(['message' => 'Address lookup is unavailable. Please retry.'], 503);
        }

        return response()->json(is_array($data) ? $data : []);
    }
}

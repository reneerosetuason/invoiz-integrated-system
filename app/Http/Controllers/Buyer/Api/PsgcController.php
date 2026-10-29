<?php

namespace App\Http\Controllers\Buyer\Api;

use App\Http\Controllers\Controller;
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

        $data = Cache::remember('psgc:'.$path, now()->addDay(), function () use ($path) {
            $response = Http::timeout(15)
                ->acceptJson()
                ->get('https://psgc.gitlab.io/api/'.$path);

            return $response->successful() ? $response->json() : [];
        });

        return response()->json(is_array($data) ? $data : []);
    }
}

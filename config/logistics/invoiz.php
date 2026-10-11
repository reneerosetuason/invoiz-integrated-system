<?php

return [

    /*
    |--------------------------------------------------------------------------
    | INVOIZ platform destinations
    |--------------------------------------------------------------------------
    |
    | Central-entry integration point for platforms that live OUTSIDE this
    | Logistics repository. Buyer and Seller applications are separate
    | systems: when their public URLs are configured here, authenticated
    | buyer/seller users are routed there automatically. When a URL is not
    | configured (null), the user stays on the public landing page with an
    | informational notice instead of a fake dashboard.
    |
    */

    'platforms' => [
        'buyer_url' => env('INVOIZ_BUYER_URL'),
        'seller_url' => env('INVOIZ_SELLER_URL'),

        // Existing Rider App deep link (also shown on the public landing
        // page). Do not invent a new protocol.
        'rider_deeplink' => env('INVOIZ_RIDER_DEEPLINK', 'invoizrider://login'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Guest system integration point
    |--------------------------------------------------------------------------
    |
    | The public marketplace (browse/search products without an account) is
    | a separate Buyer/Seller system. When its public URL is configured
    | here, the landing page links out to it. When null (default), the
    | landing page states that public browsing is coming soon instead of
    | faking a shop inside this Logistics repository.
    |
    */

    'guest' => [
        'marketplace_url' => env('INVOIZ_MARKETPLACE_URL'),
    ],

];

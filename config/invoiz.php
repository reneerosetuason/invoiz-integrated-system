<?php

return [

    /*
    |--------------------------------------------------------------------------
    | INVOIZ shared application
    |--------------------------------------------------------------------------
    |
    | Buyer, seller, admin and logistics use this Laravel application and
    | the default database connection. Web destinations are local routes;
    | there are no separate buyer/seller backend URLs to configure.
    | The external rider client must use this application's /api endpoints.
    |
    */

    'platforms' => [
        // Opens the installed rider client; it does not select a database.
        'rider_deeplink' => env('INVOIZ_RIDER_DEEPLINK', 'invoizrider://login'),
    ],

];

<?php

return [
    'geocoder_url' => env('HUB_GEOCODER_URL', 'https://photon.komoot.io/api/'),
    'router_url' => env('HUB_ROUTER_URL', 'https://router.project-osrm.org'),
    'profile' => env('HUB_ROUTER_PROFILE', 'driving'),
    'country' => env('HUB_ROUTING_COUNTRY', 'Philippines'),
    'country_code' => env('HUB_ROUTING_COUNTRY_CODE', 'PH'),
    'cache_days' => 30,
    'timeout' => 8,
];

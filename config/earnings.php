<?php

/*
|--------------------------------------------------------------------------
| Stakeholder earning defaults
|--------------------------------------------------------------------------
|
| These are the fallback rates for the logistics distribution model. Values
| stored in the `settings` table (earnings_*) override them at runtime.
|
| Pickup rider pay is the only rate multiplied by item quantity. Every other
| rate is per seller shipment (one delivery per seller per order).
|
*/

return [
    'origin_hub_fee' => 30.00,
    'pickup_rider_per_item' => 30.00,
    'inter_hub_driver_fee' => 20.00,
    'destination_hub_fee' => 30.00,
    'last_mile_rider_fee' => 50.00,
];

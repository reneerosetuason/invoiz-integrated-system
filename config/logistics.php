<?php

return [
    'transitions' => [
        'waiting_for_rider'    => ['assigned', 'cancelled'],
        'assigned'             => ['accepted', 'going_to_pickup', 'arrived_at_shop', 'picked_up', 'out_for_delivery', 'arrived_at_customer', 'delivered', 'delivery_failed', 'cancelled'],
        'accepted'             => ['going_to_pickup', 'arrived_at_shop', 'picked_up', 'out_for_delivery', 'arrived_at_customer', 'delivered', 'delivery_failed', 'cancelled'],
        'going_to_pickup'      => ['arrived_at_shop', 'picked_up', 'out_for_delivery', 'arrived_at_customer', 'delivered', 'delivery_failed', 'cancelled'],
        'arrived_at_shop'      => ['picked_up', 'out_for_delivery', 'arrived_at_customer', 'delivered', 'delivery_failed', 'cancelled'],
        'picked_up'            => ['out_for_delivery', 'arrived_at_customer', 'delivered', 'delivery_failed', 'cancelled'],
        'out_for_delivery'     => ['arrived_at_customer', 'delivered', 'delivery_failed'],
        'arrived_at_customer'  => ['delivered', 'delivery_failed'],
        'delivered'            => [],
        'delivery_failed'      => [],
        'cancelled'            => [],
    ],

    'failure_reasons' => [
        'Recipient unavailable',
        'Incorrect address',
        'Recipient refused package',
        'Vehicle problem',
        'Rider issue',
        'Package damaged',
        'Weather/road condition',
        'Other',
    ],

    'cancellation_reasons' => [
        'Customer cancelled',
        'Incorrect delivery information',
        'Duplicate delivery',
        'Package unavailable',
        'Administrative cancellation',
        'Other',
    ],

    'vehicle_capacities' => [
        'motorcycle' => 30,
        'car'        => 100,
        'van'        => 300,
        'truck'      => 500,
    ],

    'vehicle_labels' => [
        'motorcycle' => 'Motorcycle',
        'car'        => 'Car/Sedan',
        'van'        => 'Van',
        'truck'      => 'Truck',
    ],

    'delivery_otp' => [
        'ttl_minutes' => 30,
        'max_attempts' => 5,
        'length' => 6,
    ],

    // Starter PSGC region dataset for the center-application address
    // cascade (region -> province -> city/municipality -> barangay).
    'psgc_regions' => [
        '130000000' => 'National Capital Region (NCR)',
        '070000000' => 'Central Visayas (Region VII)',
        '030000000' => 'Central Luzon (Region III)',
    ],
];

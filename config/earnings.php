<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configurable Escrow & Stakeholder Earnings Distribution
    |--------------------------------------------------------------------------
    |
    | Default fallback values used when no runtime override has been saved in
    | the `settings` table by the Marketplace Admin (/admin/commission).
    |
    | Free-shipping and merchandise vouchers NEVER reduce worker or hub pay;
    | the Last-Mile Delivery Rider always deducts their fee from COD cash, and
    | the Seller absorbs all voucher and logistics deductions.
    |
    */
    'origin_hub_fee'        => 30.00, // ₱30 per seller shipment
    'pickup_rider_per_item' => 30.00, // ₱30 per item picked up from seller
    'inter_hub_driver_fee'  => 20.00, // ₱20 per seller shipment for shared inter-hub transport
    'destination_hub_fee'   => 30.00, // ₱30 per seller shipment when transferred to destination hub
    'last_mile_rider_fee'   => 50.00, // ₱50 per seller shipment (also base buyer shipping fee)
];


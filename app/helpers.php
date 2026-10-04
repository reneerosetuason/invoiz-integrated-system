<?php

use App\Models\Order;

if (! function_exists('peso')) {
    function peso($amount): string
    {
        return '₱'.number_format((float) $amount, 2);
    }
}

if (! function_exists('order_status_label')) {
    function order_status_label(string $status): string
    {
        return match ($status) {
            'pending'             => 'Pending',
            'confirmed'           => 'Confirmed',
            'processing'          => 'Processing',
            'ready_for_delivery'  => 'Ready for Delivery',
            'out_for_delivery'    => 'Out for Delivery',
            'delivered'           => 'Delivered',
            'cancelled'           => 'Cancelled',
            default               => ucfirst($status),
        };
    }
}

if (! function_exists('delivery_status_label')) {
    function delivery_status_label(string $status): string
    {
        return match ($status) {
            'waiting_for_rider' => 'Waiting for Rider',
            'assigned'          => 'Assigned',
            'picked_up'         => 'Picked Up',
            'out_for_delivery'  => 'Out for Delivery',
            'delivered'         => 'Delivered',
            'failed'            => 'Delivery Failed',
            default             => ucfirst($status),
        };
    }
}

if (! function_exists('rating_stars')) {
    function rating_stars($rating): string
    {
        $rating = (float) $rating;
        $full = floor($rating);
        $half = $rating - $full >= 0.5 ? 1 : 0;
        $empty = 5 - $full - $half;
        $out = '';
        for ($i = 0; $i < $full; $i++) {
            $out .= '<span class="rating-stars">★</span>';
        }
        if ($half) {
            $out .= '<span class="rating-stars opacity-60">★</span>';
        }
        for ($i = 0; $i < $empty; $i++) {
            $out .= '<span class="text-[#D8D5CC]">★</span>';
        }

        return $out;
    }
}
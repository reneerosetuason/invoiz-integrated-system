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
            'delivery_failed'   => 'Delivery Failed',
            'lost'              => 'Lost',
            'damaged'           => 'Damaged',
            'returned_to_seller' => 'Returned to Seller',
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
        $svgStar = function($colorCls, $opacity = false) {
            $op = $opacity ? ' opacity-60' : '';
            return '<svg class="w-3.5 h-3.5 inline-block align-middle ' . $colorCls . $op . '" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
        };
        $out = '';
        for ($i = 0; $i < $full; $i++) {
            $out .= $svgStar('text-amber-500');
        }
        if ($half) {
            $out .= $svgStar('text-amber-500', true);
        }
        for ($i = 0; $i < $empty; $i++) {
            $out .= $svgStar('text-gray-300');
        }

        return $out;
    }
}
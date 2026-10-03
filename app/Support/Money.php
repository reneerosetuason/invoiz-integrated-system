<?php

namespace App\Support;

class Money
{
    /**
     * Full amount with thousand separators, no cents: ₱97,134 / ₱1,439
     */
    public static function format($amount): string
    {
        return '₱' . number_format((float) $amount, 0);
    }
}

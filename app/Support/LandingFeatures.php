<?php

namespace App\Support;

use App\Models\Setting;

class LandingFeatures
{
    public static function ids(): array
    {
        return Setting::where('key', 'like', 'landing_featured_product_%')->where('value', '1')
            ->pluck('key')->filter(fn ($key) => preg_match('/^landing_featured_product_[0-9]+$/', $key))
            ->map(fn ($key) => (int) substr($key, strlen('landing_featured_product_')))->values()->all();
    }
}

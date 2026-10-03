<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionRate extends Model
{
    protected $fillable = [
        'category_id',
        'rate',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public static function rateForCategory(?int $categoryId): float
    {
        if ($categoryId) {
            $rate = static::where('category_id', $categoryId)->value('rate');
            if ($rate !== null) {
                return (float) $rate;
            }
        }

        return (float) (static::whereNull('category_id')->value('rate') ?? 10.00);
    }
}

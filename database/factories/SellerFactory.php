<?php

namespace Database\Factories;

use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SellerFactory extends Factory
{
    protected $model = Seller::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'store_name' => $this->faker->company() . ' Store',
            'status' => 'approved',
            'primary_color' => '#16697A',
            'accent_color' => '#F0A202',
            'logo' => null,
        ];
    }
}

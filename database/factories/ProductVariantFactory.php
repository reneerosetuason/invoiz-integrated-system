<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition()
    {
        return [
            'product_id' => Product::factory(),
            'sku' => strtoupper($this->faker->bothify('VAR-??###')),
            'attributes' => ['size' => 'M'],
            'price' => $this->faker->randomFloat(2, 5, 200),
            'stock' => $this->faker->numberBetween(0, 100),
            'weight' => $this->faker->randomFloat(2, 0.1, 5),
        ];
    }
}

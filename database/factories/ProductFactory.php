<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Seller;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition()
    {
        return [
            'seller_id' => Seller::factory(),
            'category_id' => Category::factory(),
            'name' => $this->faker->productName ?? $this->faker->words(3, true),
            'slug' => $this->faker->unique()->slug(),
            'short_description' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'brand' => $this->faker->company(),
            'sku' => strtoupper($this->faker->bothify('SKU-??###')),
            'status' => 'approved',
            'price' => $this->faker->randomFloat(2, 5, 200),
        ];
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            'Baby Clothes & Accessories',
            'Toys & Games',
            'Educational Materials',
            'Strollers & Gear',
            'Nursery Furniture',
            'Safety & Health',
            'Pet Supplies',
            'Electronics & Gadgets',
            "Women's Apparel",
            "Men's Apparel",
            'Home & Garden',
            'School Supplies',
            'Makeup',
            'Dresses',
            'Furniture',
            'Toys',
            'Sports Equipment',
            'Jewelry',
            'Gadgets',
            'Appliances',
            'Tools',
        ];

        foreach ($categories as $name) {
            Category::create([
                'name' => $name,
                'slug' => \Str::slug($name),
                'description' => $name . ' category',
                'active' => true,
            ]);
        }
    }
}

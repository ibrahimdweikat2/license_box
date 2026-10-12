<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVersion>
 */
class ProductVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'version' => fake()->unique()->numerify('1.#.#'),
            'changelog' => '- '.fake()->sentence(),
            'release_date' => now()->toDateString(),
            'is_published' => true,
        ];
    }
}

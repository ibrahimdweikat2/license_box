<?php

namespace Database\Factories;

use App\Models\License;
use App\Models\Product;
use App\Services\KeyGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<License>
 */
class LicenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'license_key' => app(KeyGenerator::class)->make(),
            'type' => 'lifetime',
            'max_activations' => 1,
            'status' => 'active',
        ];
    }

    public function expired(): static
    {
        return $this->state(['type' => 'subscription', 'expires_at' => now()->subDay()]);
    }

    public function suspended(): static
    {
        return $this->state(['status' => 'suspended']);
    }
}

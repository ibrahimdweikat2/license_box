<?php

namespace Database\Factories;

use App\Models\Activation;
use App\Models\License;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Activation>
 */
class ActivationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'license_id' => License::factory(),
            'domain' => fake()->unique()->domainName(),
            'ip' => fake()->ipv4(),
            'client_name' => fake()->name(),
            'instance_id' => Str::lower(Str::random(32)),
            'activated_at' => now(),
            'last_checked_at' => now(),
            'is_active' => true,
        ];
    }
}

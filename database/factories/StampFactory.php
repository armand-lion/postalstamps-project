<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Stamp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stamp>
 */
class StampFactory extends Factory
{
    public function definition(): array
    {
        return [
            'country_id' => Country::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'issue_year' => fake()->numberBetween(1850, 2026),
        ];
    }
}

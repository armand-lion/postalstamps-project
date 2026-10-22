<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->country(),
            'iso_code' => null,
            'entity_type' => 'country',
            'start_year' => null,
            'end_year' => null,
            'is_active' => true,
            'slug' => fake()->unique()->slug(),
            'description' => null,
        ];
    }
}

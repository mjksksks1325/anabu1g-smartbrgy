<?php

namespace Database\Factories;

use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Household>
 */
class HouseholdFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'household_number' => 'TEST-HH-'.fake()->unique()->numerify('########'),
            'address' => fake()->streetAddress(),
            'purok_id' => null,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Purok;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purok>
 */
class PurokFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Purok '.fake()->unique()->numberBetween(10, 999).' - '.fake()->streetName(),
            'color' => fake()->hexColor(),
            'is_active' => true,
        ];
    }
}

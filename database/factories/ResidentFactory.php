<?php

namespace Database\Factories;

use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Resident>
 */
class ResidentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional()->lastName(),
            'last_name' => fake()->lastName(),
            'suffix' => null,
            'date_of_birth' => fake()->dateTimeBetween('-90 years', '-1 year')->format('Y-m-d'),
            'gender' => fake()->randomElement(['Male', 'Female']),
            'civil_status' => fake()->randomElement(['Single', 'Married', 'Widowed', 'Separated']),
            'purok' => fake()->randomElement(['Purok 1 - Sampaguita', 'Purok 2 - Rosal', 'Purok 3 - Camia', 'Purok 4 - Ilang-Ilang', 'Purok 5 - Mabini']),
            'address' => fake()->streetAddress(),
            'contact_number' => '09'.fake()->numerify('#########'),
            'residency_type' => fake()->randomElement(['Homeowner', 'Renter', 'Boarder']),
            'special_groups' => [],
            'status' => 'active',
            'is_in_good_standing' => true,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['deleted_at' => now()]);
    }
}

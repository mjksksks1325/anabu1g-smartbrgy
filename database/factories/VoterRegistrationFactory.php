<?php

namespace Database\Factories;

use App\Models\Resident;
use App\Models\User;
use App\Models\VoterRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VoterRegistration>
 */
class VoterRegistrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $identifier = fake()->unique()->numerify('####-####-####');

        return [
            'resident_id' => Resident::factory(),
            'comelec_voter_number' => $identifier,
            'comelec_voter_number_hash' => VoterRegistration::identifierHash($identifier),
            'precinct_number' => fake()->numerify('####A'),
            'cluster_number' => fake()->numerify('###'),
            'registration_date' => fake()->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'status' => 'active',
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }
}

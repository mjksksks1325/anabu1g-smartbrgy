<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'incident_type' => fake()->randomElement(['Noise Complaint', 'Property Dispute', 'Theft']),
            'occurred_at' => fake()->dateTimeBetween('-3 months', 'now'),
            'location' => fake()->streetAddress(),
            'complainant_name' => fake()->name(),
            'respondent_name' => fake()->optional()->name(),
            'severity' => fake()->randomElement(['low', 'medium', 'high']),
            'details' => fake()->paragraph(),
            'status' => 'pending',
            'resolution_notes' => null,
            'attachments' => [],
            'reported_by' => User::factory(),
            'assigned_to' => null,
            'resolved_at' => null,
        ];
    }
}

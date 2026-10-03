<?php

namespace Database\Factories;

use App\Models\BarangayProtectionOrder;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BarangayProtectionOrder> */
class BarangayProtectionOrderFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['incident_id' => Incident::factory(), 'protected_person' => fake()->name(), 'respondent' => fake()->name(), 'issued_on' => today(), 'status' => 'recorded', 'issuing_authority' => 'Barangay issuing authority', 'created_by' => User::factory()->superAdmin()];
    }
}

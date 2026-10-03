<?php

namespace Database\Factories;

use App\Models\Resident;
use App\Models\ResidentRequestRestriction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ResidentRequestRestriction> */
class ResidentRequestRestrictionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['resident_id' => Resident::factory(), 'restriction_type' => 'document_request', 'reason_category' => 'Barangay review', 'internal_reason' => 'Reviewed decision recorded for testing.', 'starts_at' => now()->subMinute(), 'status' => 'pending_review', 'created_by' => User::factory()->state(['role' => 'admin'])];
    }

    public function reviewed(): static
    {
        return $this->state(fn () => ['status' => 'active', 'reviewed_by' => User::factory()->state(['role' => 'admin']), 'reviewed_at' => now()]);
    }
}

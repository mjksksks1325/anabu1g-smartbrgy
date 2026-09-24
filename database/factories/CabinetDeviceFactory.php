<?php

namespace Database\Factories;

use App\Models\CabinetDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CabinetDevice>
 */
class CabinetDeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'identifier' => 'CAB-'.fake()->unique()->numerify('####'),
            'name' => 'Cabinet '.fake()->numberBetween(1, 99),
            'last_seen_at' => null,
            'reported_status' => null,
            'component_health' => null,
            'cabinet_state' => null,
        ];
    }
}

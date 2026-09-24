<?php

namespace Database\Factories;

use App\Models\CabinetDevice;
use App\Models\FileMovementEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FileMovementEvent>
 */
class FileMovementEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'file_reference' => 'FILE-'.fake()->unique()->numerify('####'),
            'file_name' => fake()->words(3, true),
            'rfid_tag' => fake()->bothify('TAG-########'),
            'cabinet_device_id' => CabinetDevice::factory(),
            'drawer_reference' => 'A-1',
            'user_id' => User::factory(),
            'action' => FileMovementEvent::REMOVED,
            'occurred_at' => now(),
        ];
    }
}

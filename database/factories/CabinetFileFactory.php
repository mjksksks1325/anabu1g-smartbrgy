<?php

namespace Database\Factories;

use App\Models\CabinetDevice;
use App\Models\CabinetFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CabinetFile>
 */
class CabinetFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'file_reference' => 'FILE-'.fake()->unique()->bothify('####??'),
            'file_name' => fake()->words(3, true),
            'cabinet_device_id' => CabinetDevice::factory(),
            'drawer_reference' => fake()->randomElement([CabinetFile::DRAWER_1, CabinetFile::DRAWER_2]),
            'rfid_epc' => null,
            'is_active' => true,
        ];
    }
}

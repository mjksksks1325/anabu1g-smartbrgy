<?php

namespace Database\Factories;

use App\Models\CabinetDevice;
use App\Models\CabinetFile;
use App\Models\CabinetReader;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CabinetReader>
 */
class CabinetReaderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cabinet_device_id' => CabinetDevice::factory(),
            'reader_identifier' => 'READER-'.fake()->unique()->bothify('####??'),
            'model' => 'YRM100',
            'drawer_reference' => fake()->randomElement([CabinetFile::DRAWER_1, CabinetFile::DRAWER_2]),
            'is_active' => true,
        ];
    }
}

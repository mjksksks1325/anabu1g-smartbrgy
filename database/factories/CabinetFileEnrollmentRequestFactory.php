<?php

namespace Database\Factories;

use App\Models\CabinetFile;
use App\Models\CabinetFileEnrollmentRequest;
use App\Models\CabinetReader;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CabinetFileEnrollmentRequest>
 */
class CabinetFileEnrollmentRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_id' => (string) Str::uuid(),
            'cabinet_file_id' => CabinetFile::factory(),
            'cabinet_device_id' => fn (array $attributes): int => CabinetFile::query()->whereKey($attributes['cabinet_file_id'])->firstOrFail()->cabinet_device_id,
            'cabinet_reader_id' => fn (array $attributes): int => CabinetReader::factory()->create([
                'cabinet_device_id' => $attributes['cabinet_device_id'],
                'drawer_reference' => CabinetFile::query()->whereKey($attributes['cabinet_file_id'])->firstOrFail()->drawer_reference,
            ])->id,
            'reader_identifier' => fn (array $attributes): string => CabinetReader::query()->whereKey($attributes['cabinet_reader_id'])->firstOrFail()->reader_identifier,
            'folder_snapshot' => fn (array $attributes): array => CabinetFile::query()->whereKey($attributes['cabinet_file_id'])->firstOrFail()->only(['file_reference', 'file_name', 'drawer_reference']),
            'status' => 'pending',
            'rfid_epc' => null,
            'requested_by' => User::factory()->superAdmin(),
            'expires_at' => now()->addMinutes(10),
            'completed_at' => null,
        ];
    }
}

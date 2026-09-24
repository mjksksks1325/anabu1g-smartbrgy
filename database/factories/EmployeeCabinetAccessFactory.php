<?php

namespace Database\Factories;

use App\Models\EmployeeCabinetAccess;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeCabinetAccess>
 */
class EmployeeCabinetAccessFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'is_active' => false,
            'rfid_enrollment_status' => EmployeeCabinetAccess::NOT_STARTED,
            'face_enrollment_status' => EmployeeCabinetAccess::NOT_STARTED,
            'authorization_version' => 1,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\ResidentRegistrationApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ResidentRegistrationApplication> */
class ResidentRegistrationApplicationFactory extends Factory
{
    public function definition(): array
    {
        $details = ['first_name' => fake()->firstName(), 'last_name' => fake()->lastName(), 'middle_name' => null, 'suffix' => null,
            'date_of_birth' => '1995-01-15', 'gender' => 'Female', 'civil_status' => 'Single', 'purok' => 'Purok 1',
            'address' => fake()->address(), 'residency_type' => 'Renter', 'contact_number' => null, 'nationality' => 'Filipino'];
        $email = fake()->unique()->safeEmail();

        return ['reference' => 'REG-'.fake()->unique()->bothify('########????'), 'details' => $details, 'email' => $email,
            'identity_key' => ResidentRegistrationApplication::identityKey($details), 'email_key' => hash('sha256', $email),
            'photo_path' => 'resident-photos/application.jpg', 'locale' => 'en', 'status' => 'pending'];
    }
}

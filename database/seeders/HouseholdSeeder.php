<?php

namespace Database\Seeders;

use App\Actions\ManageHouseholds;
use Illuminate\Database\Seeder;

class HouseholdSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(ManageHouseholds $households): void
    {
        $households->create(['address' => 'Sample household, Anabu I-G']);
    }
}

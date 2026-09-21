<?php

use App\Models\Resident;
use App\Models\User;

test('guests cannot read the purok and demographic feed', function () {
    $this->getJson(route('admin.puroks.index'))->assertUnauthorized();
});

test('staff receive database backed puroks and accurate demographics', function () {
    $user = User::factory()->create();
    Resident::factory()->create([
        'purok' => 'Purok 1 - Sampaguita',
        'gender' => 'Male',
        'date_of_birth' => now()->subYears(65)->toDateString(),
        'special_groups' => ['PWD'],
    ]);
    Resident::factory()->create([
        'purok' => 'Purok 2 - Rosal',
        'gender' => 'Female',
        'date_of_birth' => now()->subYears(30)->toDateString(),
        'special_groups' => ['PWD', '4Ps Beneficiary'],
    ]);

    $this->actingAs($user)
        ->getJson(route('admin.puroks.index'))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Purok 1 - Sampaguita')
        ->assertJsonPath('data.0.residents_count', 1)
        ->assertJsonPath('demographics.total', 2)
        ->assertJsonPath('demographics.male', 1)
        ->assertJsonPath('demographics.female', 1)
        ->assertJsonPath('demographics.seniors', 1)
        ->assertJsonPath('demographics.special_groups.PWD', 2);
});

test('staff can add a unique purok used by resident registration', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('admin.puroks.store'), [
            'name' => '  Purok 6   -  Maharlika ',
            'color' => '#0EA5E9',
        ])
        ->assertCreated()
        ->assertJsonPath('purok.name', 'Purok 6 - Maharlika');

    $this->assertDatabaseHas('puroks', [
        'name' => 'Purok 6 - Maharlika',
        'color' => '#0EA5E9',
        'is_active' => true,
    ]);
});

test('purok creation rejects duplicate names and unsafe colors', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('admin.puroks.store'), [
            'name' => 'Purok 1 - Sampaguita',
            'color' => 'javascript:alert(1)',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'color']);
});

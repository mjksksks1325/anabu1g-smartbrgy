<?php

use App\Models\Resident;
use App\Models\User;
use App\Models\VoterRegistration;
use Illuminate\Support\Facades\DB;

function voterPayload(Resident $resident, array $overrides = []): array
{
    return [
        'resident_id' => $resident->id,
        'comelec_voter_number' => '1234-5678-9012',
        'precinct_number' => '0123A',
        'cluster_number' => '045',
        'registration_date' => '2026-05-12',
        'status' => 'active',
        ...$overrides,
    ];
}

test('guests cannot access the voter registry', function () {
    $this->getJson(route('admin.voter-registrations.index'))->assertUnauthorized();
});

test('staff can securely register an eligible resident', function () {
    $user = User::factory()->create();
    $resident = Resident::factory()->create(['date_of_birth' => '1990-01-01']);

    $this->actingAs($user)
        ->postJson(route('admin.voter-registrations.store'), voterPayload($resident))
        ->assertCreated()
        ->assertJsonPath('registration.resident_id', $resident->id)
        ->assertJsonPath('registration.masked_comelec_voter_number', '••••••••••9012')
        ->assertJsonPath('registration.integrity_valid', true)
        ->assertJsonMissing(['comelec_voter_number' => '1234-5678-9012']);

    $registration = VoterRegistration::query()->sole();
    $storedIdentifier = DB::table('voter_registrations')->value('comelec_voter_number');

    expect($storedIdentifier)->not->toBe('1234-5678-9012');
    expect($registration->audits()->count())->toBe(1);
    $this->assertDatabaseHas('voter_registrations', [
        'resident_id' => $resident->id,
        'status' => 'active',
        'created_by' => $user->id,
    ]);
});

test('voter registration rejects residents younger than 15 and duplicate identifiers', function () {
    $user = User::factory()->create();
    $minor = Resident::factory()->create(['date_of_birth' => '2012-01-01']);

    $this->actingAs($user)
        ->postJson(route('admin.voter-registrations.store'), voterPayload($minor))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('resident_id');

    $registeredResident = Resident::factory()->create(['date_of_birth' => '1985-01-01']);
    VoterRegistration::factory()->for($registeredResident)->create([
        'comelec_voter_number' => '1234-5678-9012',
        'comelec_voter_number_hash' => VoterRegistration::identifierHash('1234-5678-9012'),
    ]);
    $secondResident = Resident::factory()->create(['date_of_birth' => '1988-01-01']);

    $this->actingAs($user)
        ->postJson(route('admin.voter-registrations.store'), voterPayload($secondResident))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('comelec_voter_number');
});

test('listing separates active voters by purok and age eligibility', function () {
    $this->travelTo('2026-09-19');
    $user = User::factory()->create();
    $skOnlyResident = Resident::factory()->create([
        'date_of_birth' => '2009-09-20',
        'purok' => 'Purok 1 - Sampaguita',
    ]);
    $skAndRegularResident = Resident::factory()->create([
        'date_of_birth' => '2000-01-01',
        'purok' => 'Purok 2 - Rosal',
    ]);
    $regularOnlyResident = Resident::factory()->create([
        'date_of_birth' => '1990-01-01',
        'purok' => 'Purok 1 - Sampaguita',
    ]);
    VoterRegistration::factory()->for($skOnlyResident)->create([
        'comelec_voter_number' => '9999-8888-7777',
        'comelec_voter_number_hash' => VoterRegistration::identifierHash('9999-8888-7777'),
    ]);
    VoterRegistration::factory()->for($skAndRegularResident)->create();
    VoterRegistration::factory()->for($regularOnlyResident)->create();
    VoterRegistration::factory()->create(['status' => 'transferred']);
    VoterRegistration::factory()->for(Resident::factory()->archived())->create();

    $this->actingAs($user)
        ->getJson(route('admin.voter-registrations.index', [
            'purok' => 'Purok 1 - Sampaguita',
            'eligibility' => 'sk_only',
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.resident_id', $skOnlyResident->id)
        ->assertJsonPath('data.0.age', 16)
        ->assertJsonPath('data.0.voter_eligibility', 'sk_only')
        ->assertJsonPath('data.0.voter_eligibility_label', 'SK Voter Lamang')
        ->assertJsonPath('data.0.masked_comelec_voter_number', '••••••••••7777')
        ->assertJsonPath('summary.total', 3)
        ->assertJsonPath('summary.sk_only', 1)
        ->assertJsonPath('summary.sk_and_regular', 1)
        ->assertJsonPath('summary.regular_only', 1)
        ->assertJsonMissing(['comelec_voter_number' => '9999-8888-7777']);
});

test('a 15 year old resident can be registered as an sk voter', function () {
    $user = User::factory()->create();
    $resident = Resident::factory()->create(['date_of_birth' => '2011-05-12']);

    $this->actingAs($user)
        ->postJson(route('admin.voter-registrations.store'), voterPayload($resident))
        ->assertCreated()
        ->assertJsonPath('registration.voter_eligibility', 'sk_only');

    $this->assertDatabaseHas('voter_registrations', ['resident_id' => $resident->id]);
});

test('listing provides unregistered residents age 15 and older for the add voter form', function () {
    $this->travelTo('2026-09-19');
    $user = User::factory()->create();
    $eligibleResident = Resident::factory()->create(['date_of_birth' => '2011-09-19']);
    Resident::factory()->create(['date_of_birth' => '2012-09-20']);
    VoterRegistration::factory()->for(Resident::factory()->create([
        'date_of_birth' => '1990-01-01',
    ]))->create();

    $this->actingAs($user)
        ->getJson(route('admin.voter-registrations.index'))
        ->assertOk()
        ->assertJsonCount(1, 'eligible_residents')
        ->assertJsonPath('eligible_residents.0.id', $eligibleResident->id)
        ->assertJsonPath('eligible_residents.0.age', 15)
        ->assertJsonPath('eligible_residents.0.voter_eligibility_label', 'SK Voter Lamang');
});

test('updates use version checks and append an encrypted audit record', function () {
    $user = User::factory()->create();
    $registration = VoterRegistration::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('admin.voter-registrations.update', $registration), [
            'precinct_number' => '9999B',
            'cluster_number' => '100',
            'registration_date' => '2026-05-15',
            'status' => 'transferred',
            'version' => 1,
        ])
        ->assertOk()
        ->assertJsonPath('registration.version', 2)
        ->assertJsonPath('registration.status', 'transferred');

    expect($registration->fresh()->audits()->count())->toBe(1);

    $this->actingAs($user)
        ->patchJson(route('admin.voter-registrations.update', $registration), [
            'precinct_number' => '9999C',
            'cluster_number' => '101',
            'registration_date' => '2026-05-16',
            'status' => 'active',
            'version' => 1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('version');
});

test('tamper detection blocks updates after an out of band database change', function () {
    $user = User::factory()->create();
    $registration = VoterRegistration::factory()->create();
    DB::table('voter_registrations')->where('id', $registration->id)->update(['precinct_number' => 'TAMPERED']);

    $this->actingAs($user)
        ->patchJson(route('admin.voter-registrations.update', $registration), [
            'precinct_number' => '1000A',
            'cluster_number' => '001',
            'registration_date' => '2026-05-15',
            'status' => 'active',
            'version' => 1,
        ])
        ->assertConflict();

    expect($registration->fresh()->precinct_number)->toBe('TAMPERED');
});

test('registry export omits the private comelec number', function () {
    $user = User::factory()->create();
    VoterRegistration::factory()->create([
        'comelec_voter_number' => '5555-4444-3333',
        'comelec_voter_number_hash' => VoterRegistration::identifierHash('5555-4444-3333'),
    ]);

    $this->actingAs($user)
        ->get(route('admin.voter-registrations.export'))
        ->assertOk()
        ->assertDownload()
        ->assertDontSee('5555-4444-3333');
});

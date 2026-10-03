<?php

use App\Actions\ManageHouseholds;
use App\Models\DocumentRequest;
use App\Models\Household;
use App\Models\IssuedCertificate;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use App\Models\VoterRegistration;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/** @return array<string, mixed> */
function householdResidentPayload(array $overrides = []): array
{
    return Resident::factory()->raw([
        'first_name' => 'Maria', 'middle_name' => null, 'last_name' => 'Santos',
        'date_of_birth' => '1990-05-14', 'purok' => 'Purok 1 - Sampaguita',
        ...$overrides,
    ]);
}

it('keeps existing residents valid without assigning a household', function () {
    $resident = Resident::factory()->create();

    $this->actingAs(User::factory()->create())->getJson(route('admin.residents.show', $resident))
        ->assertOk()->assertJsonPath('household_id', null)->assertJsonPath('household_information', null)->assertJsonPath('is_household_head', false);
    expect($resident->fresh()->household)->toBeNull();
});

it('renders household controls inside the existing staff resident dashboard', function () {
    $this->actingAs(User::factory()->create(['role' => 'staff']))->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('id="modal-resident"', false)
        ->assertSee('id="res-household-id"', false)
        ->assertSee('id="res-household-relationship"', false)
        ->assertSee('id="res-household-head"', false)
        ->assertSee('id="modal-households"', false)
        ->assertSee('id="household-name"', false)
        ->assertSee('id="household-member-relationship"', false)
        ->assertSee('class="household-save-actions"', false)
        ->assertSeeInOrder(['id="household-member-relationship"', 'id="household-resident-relationship"', 'id="household-resident-head"', 'Add resident', 'class="household-save-actions"', 'Save household'], false)
        ->assertSee('onchange="selectManagedHouseholdHead(this.value)"', false)
        ->assertDontSee('id="demo-household-tbody"', false)
        ->assertSee('id="modal-purok-households"', false)
        ->assertSee('id="purok-households-tbody"', false)
        ->assertSee('Search family name')
        ->assertSee('id="purok-households-search"', false)
        ->assertSee('onsubmit="searchPurokHouseholds();return false;"', false)
        ->assertDontSee('Average Household Size')
        ->assertSee('class="stats-grid stats-grid-3" id="demographics-stats-grid"', false)
        ->assertSee('id="demo-stat-voters"', false)
        ->assertSee('Manage Households')
        ->assertSee('id="resident-pagination"', false);
});

it('creates renames and searches households by name while retaining their number and members', function () {
    $head = Resident::factory()->create();
    $this->actingAs(User::factory()->create(['role' => 'staff']));
    $response = $this->postJson(route('admin.households.store'), ['household_name' => 'Pamilya Manalac', 'address' => 'Block 1', 'household_head_resident_id' => $head->id])->assertCreated()->assertJsonPath('household.household_name', 'Pamilya Manalac');
    $id = $response->json('household.id');
    $number = $response->json('household.household_number');

    $this->getJson(route('admin.households.index', ['search' => 'Manalac']))->assertJsonPath('total', 1)->assertJsonPath('data.0.household_name', 'Pamilya Manalac');
    $this->patchJson(route('admin.households.update', $id), ['household_name' => 'Manalac Family', 'address' => 'Block 1'])->assertOk()->assertJsonPath('household.household_name', 'Manalac Family')->assertJsonPath('household.household_number', $number)->assertJsonPath('household.members.0.id', $head->id);
    $this->assertDatabaseHas('households', ['id' => $id, 'household_name' => 'Manalac Family', 'household_number' => $number]);
});

it('searches family names across pages while keeping results inside the selected purok', function () {
    $puroks = Purok::query()->limit(2)->get();
    $first = Household::factory()->create(['household_name' => 'Pamilya Manalac', 'purok_id' => $puroks[0]->id]);
    $second = Household::factory()->create(['household_name' => 'Manalac Family', 'purok_id' => $puroks[0]->id]);
    Household::factory()->create(['household_name' => 'Pamilya Santos', 'purok_id' => $puroks[0]->id]);
    Household::factory()->create(['household_name' => 'Pamilya Manalac', 'purok_id' => $puroks[1]->id]);
    $this->actingAs(User::factory()->create(['role' => 'staff']));
    $query = ['search' => 'Manalac', 'purok_id' => $puroks[0]->id, 'per_page' => 1];

    $this->getJson(route('admin.households.index', $query))->assertOk()
        ->assertJsonPath('total', 2)->assertJsonPath('last_page', 2)->assertJsonPath('data.0.id', $first->id);
    $this->getJson(route('admin.households.index', [...$query, 'page' => 2]))->assertOk()
        ->assertJsonPath('total', 2)->assertJsonPath('data.0.id', $second->id);
});

it('rejects oversized household names and allows unnamed existing households to stay valid', function () {
    $household = Household::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->postJson(route('admin.households.store'), ['household_name' => str_repeat('a', 151), 'address' => 'Block 1'])->assertUnprocessable()->assertJsonValidationErrors('household_name');
    $this->patchJson(route('admin.households.update', $household), ['household_name' => str_repeat('a', 151), 'address' => 'Block 1'])->assertUnprocessable()->assertJsonValidationErrors('household_name');
    $this->getJson(route('admin.households.show', $household))->assertOk()->assertJsonPath('household_name', null);
    $this->assertDatabaseCount('households', 1);
});

it('adds household fields to a populated database without changing existing residents', function () {
    $migration = require database_path('migrations/2026_10_03_032633_create_households_and_add_household_fields_to_residents.php');
    $migration->down();
    $resident = Resident::factory()->create(['first_name' => 'Existing', 'last_name' => 'Resident']);
    $number = $resident->resident_number;

    $migration->up();

    expect($resident->fresh())->household_id->toBeNull()->is_household_head->toBeFalse()->resident_number->toBe($number)->first_name->toBe('Existing');
    $this->assertDatabaseCount('households', 0);
});

it('creates sequential unique household numbers and permits the same address for staff', function () {
    $this->actingAs(User::factory()->create(['role' => 'staff']));

    $this->postJson(route('admin.households.store'), ['address' => 'Shared address'])->assertCreated()->assertJsonPath('household.household_number', 'HH-000001');
    $this->postJson(route('admin.households.store'), ['address' => 'Shared address'])->assertCreated()->assertJsonPath('household.household_number', 'HH-000002');

    $this->assertDatabaseCount('households', 2);
    $this->assertDatabaseHas('administrative_audits', ['action' => 'admin.households.store', 'type' => 'record']);
});

it('rejects duplicate household numbers at the database boundary', function () {
    Household::factory()->create(['household_number' => 'HH-000001']);

    expect(fn () => Household::factory()->create(['household_number' => 'HH-000001']))->toThrow(QueryException::class);
});

it('rejects changes and duplicate supplied household numbers with 422', function () {
    $household = Household::factory()->create(['household_number' => 'HH-000045']);
    $this->actingAs(User::factory()->create());

    $this->patchJson(route('admin.households.update', $household), ['address' => 'Changed', 'household_number' => 'HH-000046'])
        ->assertUnprocessable()->assertJsonValidationErrors('household_number');
    $this->postJson(route('admin.households.store'), ['address' => 'Changed', 'household_number' => 'HH-000045'])
        ->assertUnprocessable()->assertJsonValidationErrors('household_number');
    expect($household->fresh()->household_number)->toBe('HH-000045');
    $this->assertDatabaseCount('households', 1);
});

it('prevents changing an assigned number through model updates', function () {
    $household = Household::factory()->create();

    expect(fn () => $household->forceFill(['household_number' => 'CHANGED'])->save())->toThrow(ValidationException::class);
});

it('assigns residents with different surnames to the same household during registration', function () {
    $household = Household::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->postJson(route('admin.residents.store'), householdResidentPayload(['household_id' => $household->id, 'is_household_head' => true]))->assertCreated();
    $this->postJson(route('admin.residents.store'), householdResidentPayload(['last_name' => 'Garcia', 'household_id' => $household->id, 'relationship_to_household_head' => 'Spouse']))->assertCreated();

    $this->getJson(route('admin.households.show', $household))->assertOk()->assertJsonPath('household_size', 2)->assertJsonPath('head.full_name', 'Maria Santos');
    $this->assertDatabaseHas('residents', ['last_name' => 'Garcia', 'household_id' => $household->id, 'relationship_to_household_head' => 'Spouse', 'is_household_head' => false]);
});

it('creates a household inline when editing an existing resident', function () {
    $resident = Resident::factory()->create();

    $this->actingAs(User::factory()->create())->patchJson(route('admin.residents.update', $resident), householdResidentPayload([
        'new_household' => ['address' => 'New household address', 'purok_id' => Purok::query()->firstOrFail()->id],
        'is_household_head' => true,
    ]))->assertOk()->assertJsonPath('resident.is_household_head', true);

    expect($resident->fresh()->household->household_number)->toBe('HH-000001');
    $this->assertDatabaseHas('households', ['household_head_resident_id' => $resident->id, 'address' => 'New household address']);
});

it('rolls back resident and inline household creation when head assignment is invalid', function () {
    $this->actingAs(User::factory()->create())->postJson(route('admin.residents.store'), householdResidentPayload([
        'new_household' => ['address' => 'Should not remain'], 'is_household_head' => true, 'status' => 'inactive',
    ]))->assertUnprocessable()->assertJsonValidationErrors('is_household_head');

    $this->assertDatabaseCount('households', 0);
    $this->assertDatabaseCount('residents', 0);
    $this->assertDatabaseHas('household_number_sequences', ['id' => 1, 'last_number' => 0]);
});

it('preserves assignment when older clients update resident fields without household fields', function () {
    $household = Household::factory()->create();
    $resident = Resident::factory()->create();
    app(ManageHouseholds::class)->assign($resident, $household->id, 'Head', true);

    $this->actingAs(User::factory()->create())->patchJson(route('admin.residents.update', $resident), householdResidentPayload())
        ->assertOk()->assertJsonPath('resident.household_id', $household->id)->assertJsonPath('resident.is_household_head', true);
});

it('changes the designated head and removes head status from the previous head', function () {
    $household = Household::factory()->create();
    $previous = Resident::factory()->create();
    $next = Resident::factory()->create();
    $manager = app(ManageHouseholds::class);
    $manager->assign($previous, $household->id, 'Head', true);
    $manager->assign($next, $household->id, 'Child', false);

    $this->actingAs(User::factory()->create())->patchJson(route('admin.households.update', $household), [
        'address' => 'Updated household address', 'household_head_resident_id' => $next->id,
    ])->assertOk()->assertJsonPath('household.head.id', $next->id);

    expect($previous->fresh()->is_household_head)->toBeFalse();
    expect($next->fresh()->is_household_head)->toBeTrue();
    expect($household->members()->where('is_household_head', true)->count())->toBe(1);
});

it('moves a head to another household and leaves the original without a head', function () {
    $original = Household::factory()->create();
    $destination = Household::factory()->create();
    $resident = Resident::factory()->create();
    app(ManageHouseholds::class)->assign($resident, $original->id, 'Head', true);

    $this->actingAs(User::factory()->create())->patchJson(route('admin.households.members.update', [$destination, $resident]), ['is_household_head' => true])->assertOk();

    expect($original->fresh()->household_head_resident_id)->toBeNull();
    expect($destination->fresh()->household_head_resident_id)->toBe($resident->id);
    expect($resident->fresh()->household_id)->toBe($destination->id);
});

it('unsets the head checkbox in the resident form without leaving a misleading Head relationship', function () {
    $household = Household::factory()->create();
    $head = Resident::factory()->create();
    app(ManageHouseholds::class)->assign($head, $household->id, 'Head', true);

    $this->actingAs(User::factory()->create())->patchJson(route('admin.residents.update', $head), householdResidentPayload([
        'household_id' => $household->id, 'is_household_head' => false, 'relationship_to_household_head' => 'Head',
    ]))->assertOk()->assertJsonPath('resident.relationship_to_household_head', null);

    expect($head->fresh()->is_household_head)->toBeFalse();
    expect($household->fresh()->household_head_resident_id)->toBeNull();
});

it('lets staff explicitly leave a household without a head', function () {
    $household = Household::factory()->create();
    $head = Resident::factory()->create();
    app(ManageHouseholds::class)->assign($head, $household->id, 'Head', true);

    $this->actingAs(User::factory()->create())->patchJson(route('admin.households.update', $household), [
        'address' => $household->address, 'household_head_resident_id' => null,
    ])->assertOk()->assertJsonPath('household.head', null);

    expect($head->fresh()->is_household_head)->toBeFalse();
    expect($head->fresh()->household_id)->toBe($household->id);
});

it('returns 422 when both an existing and new household are selected', function () {
    $household = Household::factory()->create();

    $this->actingAs(User::factory()->create())->postJson(route('admin.residents.store'), householdResidentPayload([
        'household_id' => $household->id, 'new_household' => ['address' => 'Ambiguous assignment'],
    ]))->assertUnprocessable()->assertJsonValidationErrors('household_id');

    $this->assertDatabaseCount('households', 1);
    $this->assertDatabaseCount('residents', 0);
});

it('returns 404 for assigning an archived resident through the member endpoint', function () {
    $household = Household::factory()->create();
    $resident = Resident::factory()->archived()->create();

    $this->actingAs(User::factory()->create())->patchJson(route('admin.households.members.update', [$household, $resident]), ['is_household_head' => true])->assertNotFound();

    expect($household->fresh()->household_head_resident_id)->toBeNull();
});

it('returns 422 for a head who does not belong to the household', function () {
    $household = Household::factory()->create();
    $outsider = Resident::factory()->create();

    $this->actingAs(User::factory()->create())->patchJson(route('admin.households.update', $household), ['address' => 'Rollback', 'household_head_resident_id' => $outsider->id])
        ->assertUnprocessable()->assertJsonValidationErrors('household_head_resident_id');

    expect($household->fresh()->address)->toBe($household->address);
    expect($household->fresh()->household_head_resident_id)->toBeNull();
});

it('returns 422 when an archived or inactive resident is designated head', function (string $state) {
    $household = Household::factory()->create();
    $resident = Resident::factory()->create();
    app(ManageHouseholds::class)->assign($resident, $household->id, 'Child', false);
    if ($state === 'archived') {
        $resident->delete();
    } else {
        $resident->update(['status' => 'inactive']);
    }

    $this->actingAs(User::factory()->create())->patchJson(route('admin.households.update', $household), ['address' => $household->address, 'household_head_resident_id' => $resident->id])
        ->assertUnprocessable()->assertJsonValidationErrors('household_head_resident_id');
    expect($household->fresh()->household_head_resident_id)->toBeNull();
})->with(['archived', 'inactive']);

it('archives and restores the head without automatically assigning a replacement', function () {
    $household = Household::factory()->create();
    $head = Resident::factory()->create();
    $other = Resident::factory()->create();
    $manager = app(ManageHouseholds::class);
    $manager->assign($head, $household->id, 'Head', true);
    $manager->assign($other, $household->id, 'Child', false);
    $this->actingAs(User::factory()->create());

    $this->deleteJson(route('admin.residents.destroy', $head))->assertOk();
    $this->getJson(route('admin.households.show', $household))->assertJsonPath('head', null)->assertJsonCount(1, 'members');
    $this->patchJson(route('admin.residents.restore', $head->id))->assertOk();

    expect($head->fresh()->is_household_head)->toBeFalse();
    expect($head->fresh()->household_id)->toBe($household->id);
    expect($other->fresh()->is_household_head)->toBeFalse();
    expect($household->fresh()->household_head_resident_id)->toBeNull();
});

it('clears an inactive head when updating only existing resident fields', function () {
    $household = Household::factory()->create();
    $head = Resident::factory()->create();
    app(ManageHouseholds::class)->assign($head, $household->id, 'Head', true);

    $this->actingAs(User::factory()->create())->patchJson(route('admin.residents.update', $head), householdResidentPayload(['status' => 'inactive']))->assertOk();

    expect($head->fresh()->is_household_head)->toBeFalse();
    expect($household->fresh()->household_head_resident_id)->toBeNull();
});

it('removes membership without deleting the resident or request and certificate relationships', function () {
    $household = Household::factory()->create();
    $resident = Resident::factory()->create();
    app(ManageHouseholds::class)->assign($resident, $household->id, 'Head', true);
    $request = DocumentRequest::query()->create(['resident_id' => $resident->id, 'reference_code' => 'REQ-HH-HISTORY', 'document_type' => 'Barangay Clearance', 'full_name' => $resident->full_name, 'address' => $resident->address, 'status' => 'pending']);
    $certificate = IssuedCertificate::query()->create([
        'resident_id' => $resident->id, 'document_request_id' => $request->id, 'certificate_number' => 'CERT-HH-HISTORY',
        'certificate_type' => 'Barangay Clearance', 'resident_name' => $resident->full_name, 'address' => $resident->address,
        'issued_by' => 'Staff', 'issued_at' => now(), 'verification_code' => 'HH-VERIFY',
    ]);

    $this->actingAs(User::factory()->create())->deleteJson(route('admin.households.members.destroy', [$household, $resident]))->assertOk();

    $this->assertModelExists($resident);
    $this->assertNotSoftDeleted($resident);
    expect($resident->fresh()->household_id)->toBeNull();
    expect($household->fresh()->household_head_resident_id)->toBeNull();
    expect($request->fresh()->resident_id)->toBe($resident->id);
    expect($certificate->fresh()->resident_id)->toBe($resident->id);
    expect($certificate->fresh()->document_request_id)->toBe($request->id);
    $this->getJson(route('admin.residents.show', $resident))->assertJsonPath('document_requests.0.reference_code', 'REQ-HH-HISTORY')->assertJsonPath('issued_certificates.0.certificate_number', 'CERT-HH-HISTORY');
    $this->getJson(route('admin.request-records.index'))->assertJsonPath('data.0.document_requests.0.reference_code', 'REQ-HH-HISTORY');
});

it('returns 404 when removing a resident from a different household', function () {
    $household = Household::factory()->create();
    $other = Household::factory()->create();
    $resident = Resident::factory()->create();
    app(ManageHouseholds::class)->assign($resident, $other->id, null, false);

    $this->actingAs(User::factory()->create())->deleteJson(route('admin.households.members.destroy', [$household, $resident]))->assertNotFound();
    expect($resident->fresh()->household_id)->toBe($other->id);
});

it('keeps exact duplicate detection in place with inline household creation', function () {
    $attributes = householdResidentPayload();
    Resident::factory()->create($attributes);

    $this->actingAs(User::factory()->create())->postJson(route('admin.residents.store'), [...$attributes, 'new_household' => ['address' => 'Unused'], 'confirm_duplicate' => true])
        ->assertUnprocessable()->assertJsonValidationErrors('first_name');

    $this->assertDatabaseCount('residents', 1);
    $this->assertDatabaseCount('households', 0);
});

it('searches household number head full name and address without exposing resident contact details', function (string $term) {
    $household = Household::factory()->create(['household_number' => 'HH-000901', 'address' => 'Block 22 Lotus Street']);
    $head = Resident::factory()->create(['first_name' => 'Maria', 'middle_name' => null, 'last_name' => 'Garcia']);
    app(ManageHouseholds::class)->assign($head, $household->id, null, true);
    Household::factory()->create(['address' => 'Other street']);

    $response = $this->actingAs(User::factory()->create())->getJson(route('admin.households.index', ['search' => $term, 'per_page' => 1]));

    $response->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.household_number', 'HH-000901');
    expect($response->json('data.0.head'))->toBe(['id' => $head->id, 'full_name' => 'Maria Garcia']);
    expect($response->json('data.0'))->not->toHaveKey('members');
})->with(['HH-000901', 'Maria Garcia', 'Lotus Street']);

it('exports household columns while preserving resident filters and formula protection', function () {
    $household = Household::factory()->create(['household_number' => 'HH-000321']);
    $head = Resident::factory()->create(['first_name' => 'Head', 'middle_name' => null, 'last_name' => 'Garcia']);
    $member = Resident::factory()->create(['first_name' => 'Included', 'middle_name' => null, 'last_name' => 'Santos']);
    Resident::factory()->create(['first_name' => 'Excluded']);
    $manager = app(ManageHouseholds::class);
    $manager->assign($head, $household->id, null, true);
    $manager->assign($member, $household->id, '=DANGEROUS', false);

    $response = $this->actingAs(User::factory()->create())->get(route('admin.residents.export', ['search' => 'Included', 'status' => 'active']));

    $response->assertOk()->assertDownload('resident-records-'.today()->toDateString().'.csv');
    expect($response->streamedContent())->toContain('Household Number')->toContain('Household Head')->toContain('Relationship to Household Head')->toContain('HH-000321')->toContain('Head Garcia')->toContain("'=DANGEROUS")->not->toContain('Excluded');
});

it('provides household size and demographic query support excluding archived members', function () {
    $household = Household::factory()->create();
    $male = Resident::factory()->create(['gender' => 'Male', 'last_name' => 'Alpha']);
    $female = Resident::factory()->create(['gender' => 'Female', 'last_name' => 'Beta']);
    Resident::factory()->archived()->create(['gender' => 'Male']);
    VoterRegistration::factory()->for($female)->create(['status' => 'active']);
    app(ManageHouseholds::class)->assign($male, $household->id, null, true);
    app(ManageHouseholds::class)->assign($female, $household->id, 'Spouse', false);

    expect(Household::demographics())->toBe(['total_households' => 1, 'total_residents' => 2, 'male' => 1, 'female' => 1, 'registered_voters' => 1, 'average_household_size' => 2.0]);
    $this->actingAs(User::factory()->create())->getJson(route('admin.residents.show', $female))->assertJsonPath('household_information.household_size', 2)->assertJsonPath('household_information.members.1.registered_voter', true);
});

it('returns 422 for invalid household assignments and relationships', function (array $fields, string $error) {
    $this->actingAs(User::factory()->create())->postJson(route('admin.residents.store'), householdResidentPayload($fields))
        ->assertUnprocessable()->assertJsonValidationErrors($error);

    $this->assertDatabaseCount('residents', 0);
    $this->assertDatabaseCount('households', 0);
})->with([
    'missing household' => [['household_id' => 999999], 'household_id'],
    'head without household' => [['is_household_head' => true], 'household_id'],
    'relationship without household' => [['household_id' => null, 'relationship_to_household_head' => 'Spouse'], 'household_id'],
    'long relationship' => [['relationship_to_household_head' => str_repeat('x', 101)], 'relationship_to_household_head'],
    'missing inline address' => [['new_household' => ['purok_id' => null]], 'new_household.address'],
    'invalid inline purok' => [['new_household' => ['address' => 'Address', 'purok_id' => 999999]], 'new_household.purok_id'],
]);

it('returns 422 for missing household addresses', function () {
    $this->actingAs(User::factory()->create())->postJson(route('admin.households.store'), [])->assertUnprocessable()->assertJsonValidationErrors('address');
    $this->assertDatabaseCount('households', 0);
});

it('requires an existing head when creating a household with selected members', function () {
    $member = Resident::factory()->create();

    $this->actingAs(User::factory()->create())->postJson(route('admin.households.store'), [
        'address' => 'Address', 'members' => [['resident_id' => $member->id, 'relationship_to_household_head' => 'Child']],
    ])->assertUnprocessable()->assertJsonValidationErrors('household_head_resident_id');

    $this->assertDatabaseCount('households', 0);
    expect($member->fresh()->household_id)->toBeNull();
});

it('creates a household with a supplied number existing head and members in one save', function () {
    $head = Resident::factory()->create(['last_name' => 'Head']);
    $member = Resident::factory()->create(['last_name' => 'Member']);
    $purok = Purok::query()->firstOrFail();

    $response = $this->actingAs(User::factory()->create(['role' => 'staff']))->postJson(route('admin.households.store'), [
        'household_number' => 'HH-000100', 'address' => 'Shared address', 'purok_id' => $purok->id,
        'household_head_resident_id' => $head->id,
        'members' => [['resident_id' => $member->id, 'relationship_to_household_head' => 'Spouse']],
    ]);

    $response->assertCreated()->assertJsonPath('household.household_number', 'HH-000100')->assertJsonPath('household.head.id', $head->id)->assertJsonPath('household.household_size', 2);
    $this->assertDatabaseCount('residents', 2);
    expect($member->fresh()->household_id)->toBe($head->fresh()->household_id);
    expect($member->fresh()->relationship_to_household_head)->toBe('Spouse');
    $this->postJson(route('admin.households.store'), ['address' => 'Shared address'])->assertCreated()->assertJsonPath('household.household_number', 'HH-000101');
});

it('retains supplied barangay numbers and generates a number when blank', function () {
    $this->actingAs(User::factory()->create())->postJson(route('admin.households.store'), [
        'household_number' => 'BRGY-LOT-12', 'address' => 'Address',
    ])->assertCreated()->assertJsonPath('household.household_number', 'BRGY-LOT-12');
    $this->postJson(route('admin.households.store'), ['household_number' => null, 'address' => 'Address'])
        ->assertCreated()->assertJsonPath('household.household_number', 'HH-000001');
});

it('rolls back all household creation and membership moves if the head becomes invalid', function () {
    $old = Household::factory()->create();
    $member = Resident::factory()->create();
    $invalidHead = Resident::factory()->create(['status' => 'inactive']);
    $manager = app(ManageHouseholds::class);
    $manager->assign($member, $old->id, 'Head', true);

    expect(fn () => $manager->createWithMembers([
        'address' => 'Should roll back', 'household_head_resident_id' => $invalidHead->id,
        'members' => [['resident_id' => $member->id, 'relationship_to_household_head' => 'Spouse']],
    ]))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('households', 1);
    expect($member->fresh()->household_id)->toBe($old->id);
    expect($member->fresh()->is_household_head)->toBeTrue();
    expect($old->fresh()->household_head_resident_id)->toBe($member->id);
});

it('returns 422 for invalid selected head or household member input', function (string $invalid) {
    $resident = Resident::factory()->create();
    $payload = ['address' => 'Address', 'household_head_resident_id' => $resident->id];
    $error = match ($invalid) {
        'duplicate' => 'members.0.resident_id',
        'relationship' => 'members.0.relationship_to_household_head',
        'archived' => 'members.0.resident_id',
        'head' => 'household_head_resident_id',
    };
    if ($invalid === 'duplicate') {
        $payload['members'] = array_fill(0, 2, ['resident_id' => $resident->id, 'relationship_to_household_head' => 'Child']);
    } elseif ($invalid === 'relationship') {
        $payload['members'] = [['resident_id' => $resident->id, 'relationship_to_household_head' => str_repeat('x', 101)]];
    } elseif ($invalid === 'archived') {
        $archived = Resident::factory()->archived()->create();
        $payload['members'] = [['resident_id' => $archived->id, 'relationship_to_household_head' => 'Child']];
    } else {
        $resident->update(['status' => 'inactive']);
    }

    $this->actingAs(User::factory()->create())->postJson(route('admin.households.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors($error);
    $this->assertDatabaseCount('households', 0);
    expect($resident->fresh()->household_id)->toBeNull();
})->with(['duplicate', 'relationship', 'archived', 'head']);

it('returns the six household demographics with accurate averages and zero household handling', function () {
    $this->actingAs(User::factory()->create())->getJson(route('admin.puroks.index'))
        ->assertOk()->assertJsonPath('demographics.households.total_households', 0)->assertJsonPath('demographics.households.average_household_size', 0);
    $first = Household::factory()->create();
    Household::factory()->create();
    $male = Resident::factory()->create(['gender' => 'Male']);
    $female = Resident::factory()->create(['gender' => 'Female']);
    Resident::factory()->create(['gender' => 'Female', 'status' => 'inactive']);
    Resident::factory()->archived()->create(['gender' => 'Male']);
    VoterRegistration::factory()->for($female)->create(['status' => 'active']);
    app(ManageHouseholds::class)->assign($male, $first->id, 'Head', true);
    app(ManageHouseholds::class)->assign($female, $first->id, 'Spouse', false);

    $this->getJson(route('admin.puroks.index'))->assertOk()
        ->assertJsonPath('demographics.households.total_residents', 3)
        ->assertJsonPath('demographics.households.total_households', 2)
        ->assertJsonPath('demographics.households.male', 1)
        ->assertJsonPath('demographics.households.female', 2)
        ->assertJsonPath('demographics.households.registered_voters', 1)
        ->assertJsonPath('demographics.households.average_household_size', 1);
});

it('returns 401 for guest household access', function () {
    $household = Household::factory()->create();
    $resident = Resident::factory()->create();

    $this->getJson(route('admin.households.index'))->assertUnauthorized();
    $this->getJson(route('admin.households.show', $household))->assertUnauthorized();
    $this->postJson(route('admin.households.store'), ['address' => 'Address'])->assertUnauthorized();
    $this->patchJson(route('admin.households.update', $household), ['address' => 'Address'])->assertUnauthorized();
    $this->patchJson(route('admin.households.members.update', [$household, $resident]), [])->assertUnauthorized();
    $this->deleteJson(route('admin.households.members.destroy', [$household, $resident]))->assertUnauthorized();
});

it('returns 403 for household browsing and management by unauthorized users', function (string $role) {
    $household = Household::factory()->create();
    $resident = Resident::factory()->create();
    $this->actingAs(User::factory()->create(['role' => $role]));

    $this->getJson(route('admin.households.index'))->assertForbidden();
    $this->getJson(route('admin.households.show', $household))->assertForbidden();
    $this->postJson(route('admin.households.store'), ['address' => 'Address'])->assertForbidden();
    $this->patchJson(route('admin.households.update', $household), ['address' => 'Address'])->assertForbidden();
    $this->patchJson(route('admin.households.members.update', [$household, $resident]), [])->assertForbidden();
    $this->deleteJson(route('admin.households.members.destroy', [$household, $resident]))->assertForbidden();
    expect($resident->fresh()->household_id)->toBeNull();
})->with(['viewer', 'resident']);

<?php

use App\Models\DocumentRequest;
use App\Models\Resident;
use App\Models\User;

function residentPayload(array $overrides = []): array
{
    return [
        'first_name' => 'Maria',
        'middle_name' => 'Reyes',
        'last_name' => 'Santos',
        'suffix' => null,
        'date_of_birth' => '1990-05-14',
        'gender' => 'Female',
        'civil_status' => 'Married',
        'purok' => 'Purok 1 - Sampaguita',
        'address' => 'Block 1, Lot 2, Anabu I-G',
        'contact_number' => '09171234567',
        'residency_type' => 'Homeowner',
        'special_groups' => ['4Ps Beneficiary'],
        'status' => 'active',
        'is_in_good_standing' => true,
        ...$overrides,
    ];
}

it('requires authentication for every resident endpoint', function () {
    $resident = Resident::factory()->create();

    $this->getJson(route('admin.residents.index'))->assertUnauthorized();
    $this->get(route('admin.residents.export'))->assertRedirect(route('login'));
    $this->postJson(route('admin.residents.store'), [])->assertUnauthorized();
    $this->getJson(route('admin.residents.show', $resident))->assertUnauthorized();
    $this->patchJson(route('admin.residents.update', $resident), [])->assertUnauthorized();
    $this->deleteJson(route('admin.residents.destroy', $resident))->assertUnauthorized();
});

it('forbids non-staff users from resident records', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);

    $this->actingAs($viewer)->getJson(route('admin.residents.index'))->assertForbidden();
    $this->actingAs($viewer)->postJson(route('admin.residents.store'), residentPayload())->assertForbidden();
});

it('registers a validated resident with a server-generated number', function () {
    $staff = User::factory()->create();

    $response = $this->actingAs($staff)->postJson(route('admin.residents.store'), residentPayload());

    $response->assertCreated()
        ->assertJsonPath('resident.full_name', 'Maria Reyes Santos')
        ->assertJsonPath('resident.status', 'active');
    $resident = Resident::query()->sole();
    expect($resident->resident_number)->toStartWith('ANB-'.now()->format('Y').'-');
    $this->assertDatabaseHas('residents', [
        'id' => $resident->id,
        'contact_number' => '09171234567',
        'is_in_good_standing' => true,
    ]);
});

it('includes a newly registered active resident in the latest demographics', function () {
    $staff = User::factory()->create();

    $this->actingAs($staff)
        ->postJson(route('admin.residents.store'), residentPayload([
            'gender' => 'Female',
            'date_of_birth' => now()->subYears(65)->toDateString(),
            'special_groups' => ['PWD'],
        ]))
        ->assertCreated();

    $this->actingAs($staff)
        ->getJson(route('admin.puroks.index'))
        ->assertOk()
        ->assertJsonPath('demographics.total', 1)
        ->assertJsonPath('demographics.female', 1)
        ->assertJsonPath('demographics.seniors', 1)
        ->assertJsonPath('demographics.special_groups.PWD', 1)
        ->assertJsonPath('data.0.residents_count', 1);
});

it('rejects invalid and future resident data', function () {
    $staff = User::factory()->create();

    $this->actingAs($staff)->postJson(route('admin.residents.store'), residentPayload([
        'last_name' => '<script>alert(1)</script>',
        'date_of_birth' => now()->addDay()->toDateString(),
        'contact_number' => '12345',
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['last_name', 'date_of_birth', 'contact_number']);

    expect(Resident::query()->count())->toBe(0);
});

it('blocks an exact duplicate unless staff explicitly confirms it', function () {
    $staff = User::factory()->create();
    Resident::factory()->create(residentPayload());

    $this->actingAs($staff)->postJson(route('admin.residents.store'), residentPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('duplicate');

    $this->actingAs($staff)->postJson(route('admin.residents.store'), residentPayload([
        'confirm_duplicate' => true,
    ]))->assertCreated();

    expect(Resident::query()->count())->toBe(2);
});

it('searches and combines resident status filters with pagination', function () {
    $staff = User::factory()->create();
    Resident::factory()->create([
        'first_name' => 'Jose',
        'middle_name' => null,
        'last_name' => 'Rizal',
        'status' => 'active',
        'purok' => 'Purok 2 - Rosal',
    ]);
    Resident::factory()->create([
        'first_name' => 'Josefina',
        'middle_name' => null,
        'last_name' => 'Rizal',
        'status' => 'inactive',
        'purok' => 'Purok 2 - Rosal',
    ]);

    $this->actingAs($staff)->getJson(route('admin.residents.index', [
        'search' => 'Jose',
        'status' => 'active',
        'purok' => 'Purok 2 - Rosal',
        'per_page' => 1,
    ]))->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.full_name', 'Jose Rizal')
        ->assertJsonPath('total', 1)
        ->assertJsonPath('per_page', 1);
});

it('updates a resident without allowing a client to replace its resident number', function () {
    $staff = User::factory()->create();
    $resident = Resident::factory()->create();

    $this->actingAs($staff)->patchJson(route('admin.residents.update', $resident), residentPayload([
        'first_name' => 'Updated',
        'resident_number' => 'ATTACKER-CONTROLLED',
        'status' => 'inactive',
    ]))->assertOk()
        ->assertJsonPath('resident.full_name', 'Updated Reyes Santos');

    expect($resident->fresh())
        ->resident_number->toBe($resident->resident_number)
        ->status->toBe('inactive');
});

it('archives and restores a resident without deleting linked request history', function () {
    $staff = User::factory()->create();
    $resident = Resident::factory()->create();
    $request = DocumentRequest::query()->create([
        'resident_id' => $resident->id,
        'reference_code' => 'REQ-RESIDENT-HISTORY',
        'document_type' => 'Barangay Clearance',
        'full_name' => $resident->full_name,
        'address' => $resident->address,
        'status' => 'pending',
    ]);

    $this->actingAs($staff)->deleteJson(route('admin.residents.destroy', $resident))->assertOk();

    $this->assertSoftDeleted($resident);
    expect($request->fresh()->resident_id)->toBe($resident->id);
    $this->actingAs($staff)->getJson(route('admin.residents.index', ['status' => 'archived']))
        ->assertJsonPath('data.0.resident_number', $resident->resident_number);

    $this->actingAs($staff)->patchJson(route('admin.residents.restore', $resident->id))->assertOk();

    $this->assertNotSoftDeleted($resident);
});

it('returns server-authoritative certificate eligibility', function () {
    $staff = User::factory()->create();
    $resident = Resident::factory()->create([
        'status' => 'active',
        'is_in_good_standing' => false,
    ]);

    $this->actingAs($staff)->getJson(route('admin.residents.eligibility', [
        'resident' => $resident,
        'certificate_type' => 'Barangay Clearance',
    ]))->assertOk()
        ->assertJsonPath('eligible', false)
        ->assertJsonPath('reasons.0', 'Resident is not in good standing.');
});

it('returns paginated resident request history with authoritative standing filters', function () {
    $staff = User::factory()->create();
    $eligible = Resident::factory()->create([
        'first_name' => 'Eligible',
        'last_name' => 'Resident',
        'status' => 'active',
        'is_in_good_standing' => true,
    ]);
    $review = Resident::factory()->create([
        'first_name' => 'Review',
        'last_name' => 'Resident',
        'status' => 'active',
        'is_in_good_standing' => false,
    ]);
    DocumentRequest::query()->create([
        'resident_id' => $eligible->id,
        'reference_code' => 'REQ-HISTORY-001',
        'document_type' => 'Barangay Clearance',
        'full_name' => $eligible->full_name,
        'address' => $eligible->address,
        'status' => 'released',
    ]);
    DocumentRequest::query()->create([
        'resident_id' => $review->id,
        'reference_code' => 'REQ-HISTORY-002',
        'document_type' => 'Certificate of Residency',
        'full_name' => $review->full_name,
        'address' => $review->address,
        'status' => 'rejected',
    ]);

    $this->actingAs($staff)->getJson(route('admin.request-records.index', [
        'eligibility' => 'ineligible',
    ]))->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.resident_number', $review->resident_number)
        ->assertJsonPath('data.0.document_requests.0.reference_code', 'REQ-HISTORY-002')
        ->assertJsonPath('summary.total_requests', 2)
        ->assertJsonPath('summary.completed', 1)
        ->assertJsonPath('summary.blocked', 1)
        ->assertJsonPath('summary.needs_review', 1);
});

it('exports request history as a formula-safe csv file', function () {
    $staff = User::factory()->create();
    DocumentRequest::query()->create([
        'reference_code' => 'REQ-EXPORT-001',
        'document_type' => 'Barangay Clearance',
        'full_name' => '=DANGEROUS',
        'address' => 'Anabu I-G',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($staff)->get(route('admin.request-records.export'));

    $response->assertOk()->assertDownload('request-records-'.today()->toDateString().'.csv');
    expect($response->streamedContent())
        ->toContain('REQ-EXPORT-001')
        ->toContain("'=DANGEROUS");
});

it('exports the current resident filters as a formula-safe CSV file', function () {
    $staff = User::factory()->create();
    Resident::factory()->create([
        'first_name' => 'Maria',
        'middle_name' => null,
        'last_name' => 'Santos',
        'status' => 'active',
        'address' => '=HYPERLINK("https://example.test")',
    ]);
    Resident::factory()->create([
        'first_name' => 'Excluded',
        'last_name' => 'Resident',
        'status' => 'inactive',
    ]);

    $response = $this->actingAs($staff)->get(route('admin.residents.export', [
        'search' => 'Maria',
        'status' => 'active',
    ]));

    $response->assertOk()
        ->assertDownload('resident-records-'.today()->toDateString().'.csv');
    expect($response->streamedContent())
        ->toContain('Maria Santos')
        ->toContain("'=HYPERLINK")
        ->not->toContain('Excluded Resident');
});

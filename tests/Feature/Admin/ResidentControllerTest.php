<?php

use App\Actions\WriteRecordsSpreadsheet;
use App\Models\DocumentRequest;
use App\Models\Resident;
use App\Models\User;
use App\Models\VoterRegistration;
use Illuminate\Support\Facades\DB;

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

    $this->getJson(route('staff.residents.index'))->assertUnauthorized();
    $this->get(route('staff.residents.export'))->assertRedirect(route('login'));
    $this->postJson(route('staff.residents.store'), [])->assertUnauthorized();
    $this->getJson(route('staff.residents.show', $resident))->assertUnauthorized();
    $this->patchJson(route('staff.residents.update', $resident), [])->assertUnauthorized();
    $this->deleteJson(route('staff.residents.destroy', $resident))->assertUnauthorized();
});

it('forbids non-staff users from resident records', function () {
    $viewer = User::factory()->assignedOperations()->create(['role' => 'viewer']);

    $this->actingAs($viewer)->getJson(route('staff.residents.index'))->assertForbidden();
    $this->actingAs($viewer)->postJson(route('staff.residents.store'), residentPayload())->assertForbidden();
});

it('registers a validated resident with a server-generated number', function () {
    $staff = User::factory()->assignedOperations()->create();

    $response = $this->actingAs($staff)->postJson(route('staff.residents.store'), residentPayload());

    $response->assertCreated()
        ->assertJsonPath('resident.full_name', 'Maria Reyes Santos')
        ->assertJsonPath('resident.status', 'active');
    $resident = Resident::query()->sole();
    expect($resident->resident_number)->toStartWith('ANB-'.now()->format('Y').'-');
    $this->assertDatabaseHas('residents', [
        'id' => $resident->id,
        'contact_number' => '+639171234567',
        'is_in_good_standing' => true,
    ]);
});

it('allows staff to record nullable nationality and explicitly verified indigency', function () {
    $this->actingAs(User::factory()->assignedOperations()->create())->postJson(route('staff.residents.store'), residentPayload())
        ->assertCreated()->assertJsonPath('resident.nationality', null)->assertJsonPath('resident.is_verified_indigent', false);
    $resident = Resident::query()->sole();
    $this->patchJson(route('staff.residents.update', $resident), residentPayload(['nationality' => 'Test nationality', 'is_verified_indigent' => true]))
        ->assertOk()->assertJsonPath('resident.nationality', 'Test nationality')->assertJsonPath('resident.is_verified_indigent', true);
    $this->patchJson(route('staff.residents.update', $resident), residentPayload(['nationality' => str_repeat('x', 101), 'is_verified_indigent' => 'unverified']))
        ->assertUnprocessable()->assertJsonValidationErrors(['nationality', 'is_verified_indigent']);
});

it('returns birthdays as calendar dates and preserves them when saving the edit form', function () {
    $resident = Resident::factory()->create(residentPayload(['date_of_birth' => '1990-05-14']));
    $this->actingAs(User::factory()->assignedOperations()->create());

    $response = $this->getJson(route('staff.residents.index'))
        ->assertOk()->assertJsonPath('data.0.date_of_birth', '1990-05-14');
    $this->getJson(route('staff.residents.show', $resident))
        ->assertOk()->assertJsonPath('date_of_birth', '1990-05-14');
    $this->patchJson(route('staff.residents.update', $resident), residentPayload([
        'date_of_birth' => $response->json('data.0.date_of_birth'),
    ]))->assertOk()->assertJsonPath('resident.date_of_birth', '1990-05-14');

    expect($resident->fresh()->date_of_birth->toDateString())->toBe('1990-05-14');
});

it('includes a newly registered active resident in the latest demographics', function () {
    $staff = User::factory()->assignedOperations()->create();

    $this->actingAs($staff)
        ->postJson(route('staff.residents.store'), residentPayload([
            'gender' => 'Female',
            'date_of_birth' => now()->subYears(65)->toDateString(),
            'special_groups' => ['PWD'],
        ]))
        ->assertCreated();

    $this->actingAs($staff)
        ->getJson(route('staff.puroks.index'))
        ->assertOk()
        ->assertJsonPath('demographics.total', 1)
        ->assertJsonPath('demographics.female', 1)
        ->assertJsonPath('demographics.seniors', 1)
        ->assertJsonPath('demographics.special_groups.PWD', 1)
        ->assertJsonPath('data.0.residents_count', 1);
});

it('rejects invalid and future resident data', function () {
    $staff = User::factory()->assignedOperations()->create();

    $this->actingAs($staff)->postJson(route('staff.residents.store'), residentPayload([
        'last_name' => '<script>alert(1)</script>',
        'date_of_birth' => now()->addDay()->toDateString(),
        'contact_number' => '12345',
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['last_name', 'date_of_birth', 'contact_number']);

    expect(Resident::query()->count())->toBe(0);
});

it('rejects an exact resident duplicate even when staff confirms it', function () {
    $staff = User::factory()->assignedOperations()->create();
    $existing = Resident::factory()->create(residentPayload());

    $this->actingAs($staff)->postJson(route('staff.residents.store'), residentPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('first_name')
        ->assertJsonPath('errors.first_name.0', "An exact resident record already exists: {$existing->full_name} ({$existing->resident_number}). Review the existing record.");

    $this->actingAs($staff)->postJson(route('staff.residents.store'), residentPayload([
        'confirm_duplicate' => true,
    ]))->assertUnprocessable()->assertJsonValidationErrors('first_name');

    expect(Resident::query()->count())->toBe(1);
});

it('requires staff confirmation for a similar resident but permits a distinct middle name', function () {
    $staff = User::factory()->assignedOperations()->create();
    $existing = Resident::factory()->create(residentPayload());
    $similar = residentPayload(['middle_name' => 'Garcia']);

    $this->actingAs($staff)->postJson(route('staff.residents.store'), $similar)
        ->assertUnprocessable()->assertJsonPath('errors.duplicate.0', "Possible duplicate: {$existing->full_name} ({$existing->resident_number}). Review the existing record or confirm this is a separate resident.");
    $this->postJson(route('staff.residents.store'), [...$similar, 'confirm_duplicate' => true])->assertCreated();

    expect(Resident::query()->count())->toBe(2);
    expect($existing->fresh()->middle_name)->toBe('Reyes');
});

it('allows the same first and last name when the birth date differs', function () {
    $staff = User::factory()->assignedOperations()->create();
    Resident::factory()->create(residentPayload());

    $this->actingAs($staff)->postJson(route('staff.residents.store'), residentPayload([
        'date_of_birth' => '1991-05-14',
    ]))->assertCreated();

    expect(Resident::query()->count())->toBe(2);
});

it('detects a matching identity despite case and whitespace differences', function () {
    $staff = User::factory()->assignedOperations()->create();
    Resident::factory()->create(residentPayload(['first_name' => 'Maria  Elena', 'last_name' => 'Santos']));

    $this->actingAs($staff)->postJson(route('staff.residents.store'), residentPayload([
        'first_name' => ' MARIA Elena ', 'last_name' => 'SANTOS', 'confirm_duplicate' => true,
    ]))->assertUnprocessable()->assertJsonValidationErrors('first_name');

    expect(Resident::query()->count())->toBe(1);
});

it('does not permit a new record to replace an archived exact match', function () {
    $staff = User::factory()->assignedOperations()->create();
    $existing = Resident::factory()->create(residentPayload());
    $existing->delete();

    $this->actingAs($staff)->postJson(route('staff.residents.store'), residentPayload())
        ->assertUnprocessable()->assertJsonPath('errors.first_name.0', "An exact resident record already exists: {$existing->full_name} ({$existing->resident_number}). Review the existing record or restore it.");

    $this->assertDatabaseCount('residents', 1);
});

it('rejects a resident update that would duplicate another exact identity', function () {
    $staff = User::factory()->assignedOperations()->create();
    $existing = Resident::factory()->create(residentPayload());
    $other = Resident::factory()->create(residentPayload(['first_name' => 'Ana']));

    $this->actingAs($staff)->patchJson(route('staff.residents.update', $other), residentPayload(['confirm_duplicate' => true]))
        ->assertUnprocessable()->assertJsonValidationErrors('first_name');

    expect($other->fresh()->first_name)->toBe('Ana');
    expect($existing->fresh()->first_name)->toBe('Maria');
});

it('searches and combines resident status filters with pagination', function () {
    $staff = User::factory()->assignedOperations()->create();
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

    $this->actingAs($staff)->getJson(route('staff.residents.index', [
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

it('finds a resident by full name in any order, case, spacing or with an initial', function (string $search) {
    $staff = User::factory()->assignedOperations()->create();
    $juan = Resident::factory()->create(['first_name' => 'Juan', 'middle_name' => 'Dimaculangan', 'last_name' => 'Dela Cruz', 'suffix' => 'Jr.']);
    Resident::factory()->create(['first_name' => 'Juan', 'middle_name' => null, 'last_name' => 'Santos', 'suffix' => null]);
    Resident::factory()->create(['first_name' => 'Pedro', 'middle_name' => null, 'last_name' => 'Dela Cruz', 'suffix' => null]);

    $this->actingAs($staff)->getJson(route('staff.residents.index', ['search' => $search]))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.id', $juan->id);
})->with([
    'first then last' => 'Juan Dela Cruz',
    'last, first' => 'Dela Cruz, Juan',
    'mixed case and extra spaces' => '  jUAN   dela   CRUZ ',
    'middle initial' => 'Juan D. Cruz',
    'with suffix' => 'Juan Cruz Jr.',
]);

it('applies the same name search to request records and the case resident lookup', function () {
    $staff = User::factory()->assignedOperations()->create();
    $juan = Resident::factory()->create(['first_name' => 'Juan', 'middle_name' => null, 'last_name' => 'Dela Cruz']);
    Resident::factory()->create(['first_name' => 'Pedro', 'middle_name' => null, 'last_name' => 'Dela Cruz']);

    $this->actingAs($staff)->getJson(route('staff.request-records.index', ['search' => 'Dela Cruz Juan']))
        ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $juan->id);
    $this->getJson(route('staff.case-residents', ['search' => 'Dela Cruz Juan']))
        ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $juan->id);
});

it('shows each resident voter record status, with null for no record and no COMELEC number', function () {
    $staff = User::factory()->assignedOperations()->create();
    $registered = Resident::factory()->create(['last_name' => 'Aquino']);
    $transferred = Resident::factory()->create(['last_name' => 'Bautista']);
    $unrecorded = Resident::factory()->create(['last_name' => 'Castro']);
    VoterRegistration::factory()->create(['resident_id' => $registered->id, 'status' => 'active']);
    VoterRegistration::factory()->create(['resident_id' => $transferred->id, 'status' => 'transferred']);

    $response = $this->actingAs($staff)->getJson(route('staff.residents.index'))->assertOk();

    expect(collect($response->json('data'))->pluck('voter_status', 'id')->all())->toBe([
        $registered->id => 'active',
        $transferred->id => 'transferred',
        $unrecorded->id => null,
    ]);
    expect($response->getContent())->not->toContain('comelec');
});

it('refuses a save based on an older version instead of overwriting another staff member', function () {
    $first = User::factory()->assignedOperations()->create();
    $second = User::factory()->assignedOperations()->create();
    $this->travelTo(now()->subMinute());
    $resident = Resident::factory()->create(residentPayload());
    $this->travelBack();
    $loadedVersion = $this->actingAs($first)->getJson(route('staff.residents.index'))->json('data.0.updated_at');
    $audits = fn (): int => DB::table('administrative_audits')->where('action', 'admin.residents.update')->count();

    $this->actingAs($first)->patchJson(route('staff.residents.update', $resident), residentPayload(['address' => 'Saved first', 'expected_updated_at' => $loadedVersion]))
        ->assertOk();
    $refused = $this->actingAs($second)->patchJson(route('staff.residents.update', $resident), residentPayload(['address' => 'Saved second', 'expected_updated_at' => $loadedVersion]));

    $refused->assertStatus(409)
        ->assertJsonPath('message', 'Someone else saved changes to this resident after you opened it. Your changes were not saved.')
        ->assertJsonPath('resident.address', 'Saved first')
        ->assertJsonPath('resident.voter_status', null);
    expect($resident->fresh()->address)->toBe('Saved first')
        ->and($audits())->toBe(1);

    $this->patchJson(route('staff.residents.update', $resident), residentPayload(['address' => 'Saved second', 'expected_updated_at' => $refused->json('resident.updated_at')]))
        ->assertOk()->assertJsonPath('resident.address', 'Saved second');
    expect($audits())->toBe(2);
});

it('updates a resident without allowing a client to replace its resident number', function () {
    $staff = User::factory()->assignedOperations()->create();
    $resident = Resident::factory()->create();

    $this->actingAs($staff)->patchJson(route('staff.residents.update', $resident), residentPayload([
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
    $staff = User::factory()->assignedOperations()->create();
    $resident = Resident::factory()->create();
    $request = DocumentRequest::query()->create([
        'resident_id' => $resident->id,
        'reference_code' => 'REQ-RESIDENT-HISTORY',
        'document_type' => 'Barangay Clearance',
        'full_name' => $resident->full_name,
        'address' => $resident->address,
        'status' => 'pending',
    ]);

    $this->actingAs($staff)->deleteJson(route('staff.residents.destroy', $resident))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('super_admin_password');
    $this->actingAs($staff)->deleteJson(route('staff.residents.destroy', $resident), ['super_admin_password' => 'wrong-password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('super_admin_password');
    expect($resident->fresh()->trashed())->toBeFalse();

    $this->actingAs($staff)->deleteJson(route('staff.residents.destroy', $resident), supervisorApproval())->assertOk();

    $this->assertSoftDeleted($resident);
    expect($request->fresh()->resident_id)->toBe($resident->id);
    $this->actingAs($staff)->getJson(route('staff.residents.index', ['status' => 'archived']))
        ->assertJsonPath('data.0.resident_number', $resident->resident_number);

    $this->actingAs($staff)->patchJson(route('staff.residents.restore', $resident->id))->assertOk();

    $this->assertNotSoftDeleted($resident);
});

it('returns server-authoritative certificate eligibility', function () {
    $staff = User::factory()->assignedOperations()->create();
    $resident = Resident::factory()->create([
        'status' => 'active',
        'is_in_good_standing' => false,
    ]);

    $this->actingAs($staff)->getJson(route('staff.residents.eligibility', [
        'resident' => $resident,
        'certificate_type' => 'Barangay Clearance',
    ]))->assertOk()
        ->assertJsonPath('eligible', false)
        ->assertJsonPath('reasons.0', 'Resident is not in good standing.');
});

it('returns paginated resident request history with authoritative standing filters', function () {
    $staff = User::factory()->assignedOperations()->create();
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

    $this->actingAs($staff)->getJson(route('staff.request-records.index', [
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

it('exports request history as a branded formula-safe excel file', function () {
    $staff = User::factory()->assignedOperations()->create();
    DocumentRequest::query()->create([
        'reference_code' => 'REQ-EXPORT-001',
        'document_type' => 'Barangay Clearance',
        'full_name' => '=DANGEROUS',
        'address' => 'Anabu I-G',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($staff)->get(route('staff.request-records.export'));

    $response->assertOk()->assertDownload('request-records-'.today()->toDateString().'.xlsx');
    expect(spreadsheetSheetXml($response))
        ->toContain('Request Records')
        ->toContain('REQ-EXPORT-001')
        ->toContain('t="inlineStr"><is><t xml:space="preserve">=DANGEROUS</t>')
        ->not->toContain('<f>');
});

it('exports the current resident filters as a branded formula-safe excel file', function () {
    // A fixed name: a random one with an apostrophe is XML-escaped in the sheet and breaks the text match.
    $staff = User::factory()->assignedOperations()->create(['name' => 'Records Officer']);
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

    $response = $this->actingAs($staff)->get(route('staff.residents.export', [
        'search' => 'Maria',
        'status' => 'active',
    ]));

    $response->assertOk()
        ->assertDownload('resident-records-'.today()->toDateString().'.xlsx')
        ->assertHeader('Content-Type', WriteRecordsSpreadsheet::CONTENT_TYPE);
    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());
    expect($zip->locateName('xl/media/logo.jpg'))->not->toBeFalse();
    $zip->close();
    expect(spreadsheetSheetXml($response))
        ->toContain('Barangay Anabu I-G, City of Imus, Province of Cavite')
        ->toContain('Resident Records')
        ->toContain('Prepared by '.$staff->name)
        ->toContain('Maria Santos')
        ->toContain('=HYPERLINK(&quot;https://example.test&quot;)')
        ->not->toContain('<f>')
        ->not->toContain('Excluded Resident');
});

it('filters resident records by the special group opened from demographics', function () {
    $staff = User::factory()->assignedOperations()->create();
    Resident::factory()->create(['first_name' => 'Pwd', 'last_name' => 'Member', 'special_groups' => ['PWD'], 'date_of_birth' => '1990-01-01']);
    Resident::factory()->create(['first_name' => 'Solo', 'last_name' => 'Parent', 'special_groups' => ['Solo Parent'], 'date_of_birth' => '1990-01-01']);
    Resident::factory()->create(['first_name' => 'Elder', 'last_name' => 'Senior', 'special_groups' => [], 'date_of_birth' => today()->subYears(65)->toDateString()]);

    $this->actingAs($staff)->getJson(route('staff.residents.index', ['special_group' => 'PWD']))
        ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.first_name', 'Pwd');
    $this->getJson(route('staff.residents.index', ['special_group' => 'Senior Citizen']))
        ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.first_name', 'Elder');
    $this->getJson(route('staff.residents.index', ['special_group' => 'Unknown Group']))
        ->assertOk()->assertJsonPath('total', 3);
});

it('accepts only 09 or +639 mobile numbers when creating or editing a resident', function (string $contactNumber, bool $valid) {
    $staff = User::factory()->assignedOperations()->create();
    $resident = Resident::factory()->create(['contact_number' => null]);

    $store = $this->actingAs($staff)->postJson(route('staff.residents.store'), residentPayload(['contact_number' => $contactNumber]));
    $update = $this->patchJson(route('staff.residents.update', $resident), [...residentPayload(['first_name' => $resident->first_name, 'last_name' => $resident->last_name, 'date_of_birth' => $resident->date_of_birth->toDateString()]), 'contact_number' => $contactNumber]);

    if ($valid) {
        $store->assertCreated();
        $update->assertOk();
    } else {
        $store->assertJsonValidationErrors(['contact_number' => 'Enter 11 digits starting with 09']);
        $update->assertJsonValidationErrors(['contact_number' => 'Enter 11 digits starting with 09']);
    }
})->with([['09171234567', true], ['+639171234567', true], ['9171234567', false], ['0817123456', false], ['+6391712345678', false]]);

it('stores every accepted mobile number in the international +639XXXXXXXXX format', function (string $typed) {
    $staff = User::factory()->assignedOperations()->create();
    $resident = Resident::factory()->create(['contact_number' => null]);

    $created = $this->actingAs($staff)->postJson(route('staff.residents.store'), residentPayload(['contact_number' => $typed]))->assertCreated();
    $this->patchJson(route('staff.residents.update', $resident), [...residentPayload(['first_name' => $resident->first_name, 'last_name' => $resident->last_name, 'date_of_birth' => $resident->date_of_birth->toDateString()]), 'contact_number' => $typed])->assertOk();

    expect(Resident::query()->whereKey($created->json('resident.id') ?? $created->json('id'))->value('contact_number'))->toBe('+639171234567')
        ->and($resident->fresh()->contact_number)->toBe('+639171234567');
})->with(['09171234567', '+639171234567', '+63 917 123 4567', '0917-123-4567']);

test('resident records show which certificate details each record lacks, and can list only those records', function () {
    $staff = User::factory()->assignedOperations()->create();
    $complete = Resident::factory()->create(['last_name' => 'Complete', 'nationality' => 'Filipino', 'photo_path' => 'resident-photos/complete.jpg']);
    $incomplete = Resident::factory()->create(['last_name' => 'Incomplete', 'nationality' => null, 'photo_path' => null]);

    $rows = collect($this->actingAs($staff)->getJson(route('staff.residents.index'))->assertOk()->json('data'))->keyBy('id');
    expect($rows[$complete->id]['missing_certificate_details'])->toBe([])
        ->and($rows[$incomplete->id]['missing_certificate_details'])->toBe(['nationality', 'photo']);

    $this->getJson(route('staff.residents.index', ['status' => 'incomplete']))->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $incomplete->id);
    expect($incomplete->fresh()->nationality)->toBeNull();
});

test('the eligibility check keeps missing details and pending verification apart from actual ineligibility', function () {
    $staff = User::factory()->assignedOperations()->create();
    $resident = Resident::factory()->create(['nationality' => null, 'photo_path' => null, 'is_in_good_standing' => true, 'is_verified_indigent' => false]);
    VoterRegistration::factory()->create(['resident_id' => $resident->id, 'status' => 'active']);

    $this->actingAs($staff)->getJson(route('staff.residents.eligibility', [$resident, 'certificate_type' => 'Certificate of Indigency']))
        ->assertOk()->assertJsonPath('eligible', false)
        ->assertJsonPath('disqualifications', [])
        ->assertJsonPath('verifications', [Resident::INDIGENCY_NOT_VERIFIED])
        ->assertJsonPath('missing_details', ['Nationality', 'Resident photo']);

    $this->getJson(route('staff.residents.eligibility', [$resident, 'certificate_type' => 'Barangay Clearance']))
        ->assertOk()->assertJsonPath('eligible', true)->assertJsonPath('disqualifications', [])
        ->assertJsonPath('verifications', [])->assertJsonPath('missing_details', []);

    $resident->update(['is_in_good_standing' => false]);
    $this->getJson(route('staff.residents.eligibility', [$resident, 'certificate_type' => 'Barangay Clearance']))
        ->assertOk()->assertJsonPath('eligible', false)->assertJsonPath('disqualifications', ['Resident is not in good standing.']);
});

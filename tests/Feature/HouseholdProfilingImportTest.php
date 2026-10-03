<?php

use App\Actions\ImportHouseholdProfiling;
use App\Models\DocumentRequest;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use App\Models\VoterRegistration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/** @return array<string, string> */
function profilingRow(array $overrides = []): array
{
    return array_replace([
        'household_group' => 'Family 1', 'household_number' => '', 'is_household_head' => 'yes', 'relationship_to_household_head' => 'Head',
        'first_name' => 'Juan', 'middle_name' => '', 'last_name' => 'Dela Cruz', 'suffix' => '', 'date_of_birth' => '1980-05-14',
        'gender' => 'Male', 'civil_status' => 'Married', 'purok' => 'Purok 1 - Sampaguita', 'address' => 'Block 1 Lot 1',
        'contact_number' => '', 'residency_type' => 'Homeowner',
    ], $overrides);
}

/** @param list<array<string, string>> $rows */
function profilingFile(array $rows): UploadedFile
{
    $stream = fopen('php://temp', 'r+');
    fputcsv($stream, array_keys($rows[0]));
    foreach ($rows as $row) {
        fputcsv($stream, array_values($row));
    }
    rewind($stream);
    $csv = stream_get_contents($stream);
    fclose($stream);

    return UploadedFile::fake()->createWithContent('profiling.csv', $csv);
}

/** @return array<string, mixed> */
function profilingPayload(array $preview): array
{
    return ['token' => $preview['token'], 'mapping' => $preview['mapping'], 'rows' => array_map(fn (array $row): array => Arr::only($row, ['id', 'fields', 'is_head', 'action', 'resident_id', 'confirm_duplicate']), $preview['rows'])];
}

it('places import and management in Resident Records and keeps Demographics read only', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))->assertOk();
    $html = $response->getContent();
    $demographics = substr($html, strpos($html, 'id="screen-demographics"'), strpos($html, 'id="screen-records"') - strpos($html, 'id="screen-demographics"'));
    $records = substr($html, strpos($html, 'id="screen-records"'), strpos($html, 'id="screen-voters"') - strpos($html, 'id="screen-records"'));

    expect($records)->toContain('Import Residents / Household Profiling')->toContain('openHouseholdProfilingImport()')->toContain('Manage Households');
    expect($demographics)->not->toContain('openHouseholdProfilingImport()')->not->toContain('openHouseholdManagement()')->not->toContain('openNewHouseholdFromDemographics()');
});

it('previews CSV households without creating records and imports to the shared demographic tables', function () {
    $this->actingAs(User::factory()->create(['role' => 'staff']));
    $file = profilingFile([profilingRow(), profilingRow(['first_name' => 'Maria', 'last_name' => 'Garcia', 'date_of_birth' => '1981-05-14', 'gender' => 'Female', 'is_household_head' => 'no', 'relationship_to_household_head' => 'Spouse'])]);

    $preview = $this->postJson(route('admin.resident-profiling-imports.preview'), ['file' => $file])->assertOk()->assertJsonPath('summary.households', 1)->assertJsonPath('summary.residents', 2)->assertJsonPath('summary.heads', 1)->assertJsonPath('summary.ready', 2);
    $this->assertDatabaseCount('residents', 0);
    $this->assertDatabaseCount('households', 0);
    $payload = profilingPayload($preview->json());
    $this->postJson(route('admin.resident-profiling-imports.store'), $payload)->assertCreated()->assertJsonPath('result.created', 2);

    $this->getJson(route('admin.residents.index', ['search' => 'Juan', 'status' => 'active', 'per_page' => 1]))->assertJsonPath('total', 1)->assertJsonPath('data.0.household.household_number', 'HH-000001');
    $this->getJson(route('admin.puroks.index'))->assertJsonPath('data.0.households_count', 1)->assertJsonPath('demographics.households.total_residents', 2)->assertJsonPath('demographics.households.total_households', 1)->assertJsonPath('demographics.households.average_household_size', 2);
    $this->getJson(route('admin.households.show', Household::query()->sole()))->assertJsonPath('household_size', 2)->assertJsonPath('head.full_name', 'Juan Dela Cruz');
    $this->get(route('admin.residents.export'))->assertOk()->assertDownload('resident-records-'.today()->toDateString().'.csv');
    $this->assertDatabaseHas('administrative_audits', ['action' => 'admin.resident-profiling-imports.store']);
    $this->postJson(route('admin.resident-profiling-imports.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('file');
});

it('accepts browser multipart uploads with blank default purok and returns missing grouping for review', function () {
    $this->actingAs(User::factory()->create(['role' => 'staff']));

    $this->withHeader('Accept', 'application/json')->post(route('admin.resident-profiling-imports.preview'), ['file' => profilingFile([profilingRow(['household_group' => ''])]), 'purok' => ''])
        ->assertOk()->assertJsonPath('rows.0.fields.first_name', 'Juan')->assertJsonPath('rows.0.fields.purok', 'Purok 1 - Sampaguita')->assertJsonPath('summary.invalid', 1);

    $this->assertDatabaseCount('residents', 0);
    $this->assertDatabaseCount('households', 0);
});

it('imports optional household names without grouping separate families by their name', function () {
    $this->actingAs(User::factory()->create());
    $preview = $this->postJson(route('admin.resident-profiling-imports.preview'), ['file' => profilingFile([profilingRow(['household_name' => 'Santos Family']), profilingRow(['household_name' => 'Santos Family', 'household_group' => 'Family 2', 'first_name' => 'Maria'])])])->assertOk()->assertJsonPath('summary.households', 2)->json();
    $this->postJson(route('admin.resident-profiling-imports.store'), profilingPayload($preview))->assertCreated();
    expect(Household::query()->where('household_name', 'Santos Family')->count())->toBe(2);
});

it('requires household names to agree within a reviewed import group', function () {
    $this->actingAs(User::factory()->create());
    $preview = $this->postJson(route('admin.resident-profiling-imports.preview'), ['file' => profilingFile([profilingRow(['household_name' => 'Santos Family']), profilingRow(['household_name' => 'Other Family', 'first_name' => 'Maria', 'is_household_head' => 'no', 'relationship_to_household_head' => 'Spouse'])])])->assertJsonPath('summary.invalid', 2)->json();
    $this->postJson(route('admin.resident-profiling-imports.store'), profilingPayload($preview))->assertUnprocessable();
    $this->assertDatabaseCount('households', 0);
});

it('requires explicit reviewed grouping rather than matching addresses or surnames', function () {
    $this->actingAs(User::factory()->create());
    $preview = $this->postJson(route('admin.resident-profiling-imports.preview'), ['file' => profilingFile([profilingRow(['household_group' => '']), profilingRow(['household_group' => '', 'first_name' => 'Jose', 'date_of_birth' => '1982-01-01'])])])->assertOk();
    $payload = profilingPayload($preview->json());

    $this->postJson(route('admin.resident-profiling-imports.store'), $payload)->assertUnprocessable();
    $payload['rows'][0]['fields']['household_group'] = 'Reviewed A';
    $payload['rows'][1]['fields']['household_group'] = 'Reviewed B';
    $this->postJson(route('admin.resident-profiling-imports.review'), $payload)->assertJsonPath('summary.households', 2)->assertJsonPath('summary.invalid', 0);
    $this->postJson(route('admin.resident-profiling-imports.store'), $payload)->assertCreated();
    $this->assertDatabaseCount('households', 2);
    expect(Household::query()->pluck('address')->unique()->count())->toBe(1);
});

it('detects exact and archived duplicates and never creates them after confirmation', function (bool $archived) {
    $existing = Resident::factory()->create(['first_name' => 'Juan', 'middle_name' => null, 'last_name' => 'Dela Cruz', 'suffix' => null, 'date_of_birth' => '1980-05-14']);
    if ($archived) {
        $existing->delete();
    }
    $this->actingAs(User::factory()->create());
    $preview = $this->postJson(route('admin.resident-profiling-imports.preview'), ['file' => profilingFile([profilingRow()])])->assertOk()->assertJsonPath('rows.0.matches.0.exact', true)->assertJsonPath('rows.0.matches.0.archived', $archived);
    $payload = profilingPayload($preview->json());
    $payload['rows'][0]['action'] = 'create';
    $payload['rows'][0]['confirm_duplicate'] = true;

    $this->postJson(route('admin.resident-profiling-imports.store'), $payload)->assertUnprocessable();
    $this->assertDatabaseCount('residents', 1);
    $this->assertDatabaseCount('households', 0);
})->with([false, true]);

it('links an existing resident without overwriting verified fields or request history', function () {
    $existing = Resident::factory()->create(['first_name' => 'Juan', 'middle_name' => null, 'last_name' => 'Dela Cruz', 'suffix' => null, 'date_of_birth' => '1980-05-14', 'address' => 'Verified address', 'contact_number' => '09170000000']);
    $request = DocumentRequest::query()->create(['resident_id' => $existing->id, 'reference_code' => 'REQ-PROFILE-LINK', 'document_type' => 'Barangay Clearance', 'full_name' => $existing->full_name, 'address' => $existing->address, 'status' => 'pending']);
    VoterRegistration::factory()->for($existing)->create(['status' => 'active']);
    $this->actingAs(User::factory()->create());
    $preview = $this->postJson(route('admin.resident-profiling-imports.preview'), ['file' => profilingFile([profilingRow(['address' => 'Uploaded different address'])])])->json();
    $payload = profilingPayload($preview);
    $payload['rows'][0]['action'] = 'link';
    $payload['rows'][0]['resident_id'] = $existing->id;

    $this->postJson(route('admin.resident-profiling-imports.store'), $payload)->assertCreated()->assertJsonPath('result.linked', 1)->assertJsonPath('result.created', 0);

    $this->assertDatabaseCount('residents', 1);
    expect($existing->fresh())->address->toBe('Verified address')->contact_number->toBe('09170000000')->is_household_head->toBeTrue();
    expect($request->fresh()->resident_id)->toBe($existing->id);
    $this->getJson(route('admin.residents.show', $existing))->assertJsonPath('document_requests.0.reference_code', 'REQ-PROFILE-LINK');
    $this->getJson(route('admin.puroks.index'))->assertJsonPath('demographics.households.registered_voters', 1);
});

it('requires confirmation for a possible duplicate with a distinct middle name', function () {
    Resident::factory()->create(['first_name' => 'Juan', 'middle_name' => 'Reyes', 'last_name' => 'Dela Cruz', 'suffix' => null, 'date_of_birth' => '1980-05-14']);
    $this->actingAs(User::factory()->create());
    $preview = $this->postJson(route('admin.resident-profiling-imports.preview'), ['file' => profilingFile([profilingRow(['middle_name' => 'Garcia'])])])->json();
    $payload = profilingPayload($preview);
    $payload['rows'][0]['action'] = 'create';

    $this->postJson(route('admin.resident-profiling-imports.store'), $payload)->assertUnprocessable();
    $payload['rows'][0]['confirm_duplicate'] = true;
    $this->postJson(route('admin.resident-profiling-imports.store'), $payload)->assertCreated();
    $this->assertDatabaseCount('residents', 2);
});

it('lets staff skip invalid rows without creating their residents or households', function () {
    $this->actingAs(User::factory()->create());
    $preview = $this->postJson(route('admin.resident-profiling-imports.preview'), ['file' => profilingFile([profilingRow(), profilingRow(['first_name' => '', 'is_household_head' => 'no', 'relationship_to_household_head' => 'Child'])])])->json();
    $payload = profilingPayload($preview);
    $payload['rows'][1]['action'] = 'skip';

    $this->postJson(route('admin.resident-profiling-imports.store'), $payload)->assertCreated()->assertJsonPath('result.skipped', 1);
    $this->assertDatabaseCount('residents', 1);
});

it('rejects multiple heads and allows staff to correct the reviewed head selection', function () {
    $this->actingAs(User::factory()->create());
    $preview = $this->postJson(route('admin.resident-profiling-imports.preview'), ['file' => profilingFile([profilingRow(), profilingRow(['first_name' => 'Maria', 'date_of_birth' => '1981-01-01'])])])->json();
    $payload = profilingPayload($preview);

    $this->postJson(route('admin.resident-profiling-imports.store'), $payload)->assertUnprocessable();
    $payload['rows'][1]['is_head'] = false;
    $payload['rows'][1]['fields']['relationship_to_household_head'] = 'Spouse';
    $this->postJson(route('admin.resident-profiling-imports.store'), $payload)->assertCreated();
    expect(Resident::query()->where('is_household_head', true)->count())->toBe(1);
});

it('rechecks identities created after preview before saving', function () {
    $this->actingAs(User::factory()->create());
    $preview = $this->postJson(route('admin.resident-profiling-imports.preview'), ['file' => profilingFile([profilingRow()])])->json();
    Resident::factory()->create(['first_name' => 'Juan', 'middle_name' => null, 'last_name' => 'Dela Cruz', 'suffix' => null, 'date_of_birth' => '1980-05-14']);

    $this->postJson(route('admin.resident-profiling-imports.store'), profilingPayload($preview))->assertUnprocessable();
    $this->assertDatabaseCount('residents', 1);
    $this->assertDatabaseCount('households', 0);
});

it('rolls back earlier households and residents when a later linked record is archived after review', function () {
    $existing = Resident::factory()->create(['first_name' => 'Second', 'middle_name' => null, 'last_name' => 'Resident', 'suffix' => null, 'date_of_birth' => '1980-05-14']);
    $this->actingAs(User::factory()->create());
    $initial = $this->postJson(route('admin.resident-profiling-imports.preview'), ['file' => profilingFile([profilingRow(), profilingRow(['household_group' => 'Family 2', 'first_name' => 'Second', 'last_name' => 'Resident'])])])->json();
    $payload = profilingPayload($initial);
    $payload['rows'][1]['action'] = 'link';
    $payload['rows'][1]['resident_id'] = $existing->id;
    $reviewed = $this->postJson(route('admin.resident-profiling-imports.review'), $payload)->assertJsonPath('summary.invalid', 0)->json();
    $existing->delete();

    expect(fn () => app(ImportHouseholdProfiling::class)->save($reviewed))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('households', 0);
    $this->assertDatabaseCount('residents', 1);
});

it('rejects expired previews and access by a different staff session', function () {
    $this->actingAs(User::factory()->create());
    $preview = $this->postJson(route('admin.resident-profiling-imports.preview'), ['file' => profilingFile([profilingRow()])])->json();
    $this->travel(31)->minutes();

    $this->postJson(route('admin.resident-profiling-imports.store'), profilingPayload($preview))->assertUnprocessable()->assertJsonValidationErrors('file');
    $this->travelBack();
    $this->actingAs(User::factory()->create())->postJson(route('admin.resident-profiling-imports.store'), profilingPayload($preview))->assertUnprocessable()->assertJsonValidationErrors('file');
});

it('allows remapping columns and correcting missing resident fields in preview', function () {
    $row = profilingRow(['first_name' => '', 'household_group' => '']);
    $this->actingAs(User::factory()->create());
    $preview = $this->postJson(route('admin.resident-profiling-imports.preview'), ['file' => profilingFile([$row])])->json();
    $payload = profilingPayload($preview);
    $payload['rows'][0]['fields']['first_name'] = 'Reviewed';
    $payload['rows'][0]['fields']['household_group'] = 'Reviewed family';

    $this->postJson(route('admin.resident-profiling-imports.review'), $payload)->assertJsonPath('summary.invalid', 0);
    $this->postJson(route('admin.resident-profiling-imports.store'), $payload)->assertCreated();
    $this->assertDatabaseHas('residents', ['first_name' => 'Reviewed']);
});

it('filters read only households by purok for the demographic hierarchy', function () {
    $purok = Purok::query()->firstOrFail();
    Household::factory()->create(['purok_id' => $purok->id]);
    Household::factory()->create();

    $this->actingAs(User::factory()->create())->getJson(route('admin.households.index', ['purok_id' => $purok->id]))->assertJsonPath('total', 1)->assertJsonPath('data.0.purok_id', $purok->id);
});

it('refuses unauthorized import preview review and save', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role]));
    $this->postJson(route('admin.resident-profiling-imports.preview'), ['file' => profilingFile([profilingRow()])])->assertForbidden();
    $this->postJson(route('admin.resident-profiling-imports.review'), [])->assertForbidden();
    $this->postJson(route('admin.resident-profiling-imports.store'), [])->assertForbidden();
})->with(['viewer', 'resident']);

it('requires authentication and rejects unsupported or empty files', function () {
    $this->postJson(route('admin.resident-profiling-imports.preview'), [])->assertUnauthorized();
    $this->postJson(route('admin.resident-profiling-imports.review'), [])->assertUnauthorized();
    $this->postJson(route('admin.resident-profiling-imports.store'), [])->assertUnauthorized();
    $this->actingAs(User::factory()->create())->postJson(route('admin.resident-profiling-imports.preview'), ['file' => UploadedFile::fake()->createWithContent('old.xls', 'data')])->assertUnprocessable()->assertJsonValidationErrors('file');
    $this->postJson(route('admin.resident-profiling-imports.preview'), ['file' => UploadedFile::fake()->createWithContent('empty.csv', '')])->assertUnprocessable()->assertJsonValidationErrors('file');
});

it('flags possible duplicates within the uploaded file before saving', function () {
    $this->actingAs(User::factory()->create());
    $preview = $this->postJson(route('admin.resident-profiling-imports.preview'), ['file' => profilingFile([profilingRow(['middle_name' => 'Reyes']), profilingRow(['middle_name' => 'Garcia', 'is_household_head' => 'no', 'relationship_to_household_head' => 'Sibling'])])])->assertJsonPath('summary.duplicates', 1)->assertJsonPath('summary.invalid', 1)->json();
    $payload = profilingPayload($preview);
    $this->postJson(route('admin.resident-profiling-imports.store'), $payload)->assertUnprocessable();
    $payload['rows'][1]['confirm_duplicate'] = true;
    $this->postJson(route('admin.resident-profiling-imports.store'), $payload)->assertCreated();
    $this->assertDatabaseCount('residents', 2);
});

it('rejects distinct groups that reuse a household number', function () {
    $this->actingAs(User::factory()->create());
    $preview = $this->postJson(route('admin.resident-profiling-imports.preview'), ['file' => profilingFile([profilingRow(['household_number' => 'HH-42']), profilingRow(['household_group' => 'Other family', 'household_number' => 'HH-42', 'first_name' => 'Maria'])])])->assertJsonPath('summary.invalid', 1)->json();
    $this->postJson(route('admin.resident-profiling-imports.store'), profilingPayload($preview))->assertUnprocessable();
    $this->assertDatabaseCount('households', 0);
});

it('validates household fields when linking and rejects inactive heads', function () {
    $existing = Resident::factory()->create(['first_name' => 'Juan', 'middle_name' => null, 'last_name' => 'Dela Cruz', 'suffix' => null, 'date_of_birth' => '1980-05-14', 'status' => 'inactive']);
    $this->actingAs(User::factory()->create());
    $preview = $this->postJson(route('admin.resident-profiling-imports.preview'), ['file' => profilingFile([profilingRow()])])->json();
    $payload = profilingPayload($preview);
    $payload['rows'][0]['action'] = 'link';
    $payload['rows'][0]['resident_id'] = $existing->id;
    $this->postJson(route('admin.resident-profiling-imports.review'), $payload)->assertJsonPath('summary.invalid', 1);
    $existing->update(['status' => 'active']);
    $payload['rows'][0]['fields']['relationship_to_household_head'] = str_repeat('x', 101);
    $this->postJson(route('admin.resident-profiling-imports.store'), $payload)->assertUnprocessable();
    $this->assertDatabaseCount('households', 0);
});

it('links a reviewed existing household without making another household', function () {
    $purok = Purok::query()->where('name', 'Purok 1 - Sampaguita')->firstOrFail();
    Household::factory()->create(['household_number' => 'HH-123', 'address' => 'Block 1 Lot 1', 'purok_id' => $purok->id]);
    $this->actingAs(User::factory()->create());
    $preview = $this->postJson(route('admin.resident-profiling-imports.preview'), ['file' => profilingFile([profilingRow(['household_number' => 'HH-123'])])])->assertJsonPath('summary.invalid', 0)->json();
    $this->postJson(route('admin.resident-profiling-imports.store'), profilingPayload($preview))->assertCreated();
    $this->assertDatabaseCount('households', 1);
    $this->assertDatabaseHas('residents', ['household_id' => Household::query()->sole()->id, 'is_household_head' => true]);
});

it('rejects malformed tokens unknown fields and unrelated links without writing records', function () {
    $unrelated = Resident::factory()->create();
    $this->actingAs(User::factory()->create());
    $preview = $this->postJson(route('admin.resident-profiling-imports.preview'), ['file' => profilingFile([profilingRow()])])->json();
    $payload = profilingPayload($preview);
    $this->postJson(route('admin.resident-profiling-imports.store'), array_replace($payload, ['token' => ['malformed']]))->assertUnprocessable()->assertJsonValidationErrors('token');
    $unknown = $payload;
    $unknown['rows'][0]['fields']['status'] = 'active';
    $this->postJson(route('admin.resident-profiling-imports.store'), $unknown)->assertUnprocessable()->assertJsonValidationErrors('rows.0.fields');
    $payload['rows'][0]['action'] = 'link';
    $payload['rows'][0]['resident_id'] = $unrelated->id;
    $this->postJson(route('admin.resident-profiling-imports.store'), $payload)->assertUnprocessable();
    $this->assertDatabaseCount('residents', 1);
    $this->assertDatabaseCount('households', 0);
});

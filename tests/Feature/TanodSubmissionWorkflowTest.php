<?php

use App\Models\Incident;
use App\Models\ResidentRequestRestriction;
use App\Models\User;
use App\StaffPermissions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function ordinaryTanodReport(array $overrides = []): array
{
    return [
        'incident_type_selection' => 'Noise Complaint', 'occurred_date' => today()->toDateString(),
        'occurred_time' => '10:30', 'location' => 'Reported street location', 'severity' => 'low',
        'details' => 'I observed loud music coming from a nearby house.', ...$overrides,
    ];
}

test('tanod submits evidence and receives only a reference confirmation which can be refreshed safely', function () {
    Storage::fake('local');
    $tanod = User::factory()->create(['role' => 'staff', 'staff_permissions' => StaffPermissions::presets()['Tanod']]);

    $this->actingAs($tanod)->post(route('staff.incidents.store'), ordinaryTanodReport([
        'purok' => 'Purok 1', 'immediate_action' => 'Asked those present to lower the volume.',
        'attachments' => [UploadedFile::fake()->image('evidence.jpg')],
    ]))->assertRedirect(route('staff.incidents.index', ['submitted' => 1]))->assertSessionHasNoErrors();

    $incident = Incident::query()->sole();
    $this->assertDatabaseHas('incidents', ['id' => $incident->id, 'reported_by' => $tanod->id, 'updated_by' => $tanod->id, 'status' => 'open', 'is_sensitive' => false, 'assigned_to' => null, 'resolved_at' => null]);
    expect($incident->details)->toBe("I observed loud music coming from a nearby house.\n\nPurok (reported): Purok 1\n\nImmediate action reported by submitter: Asked those present to lower the volume.");
    expect($incident->occurred_at->format('H:i'))->toBe('10:30');
    Storage::disk('local')->assertExists($incident->attachments[0]['path']);
    $this->assertDatabaseHas('incident_events', ['incident_id' => $incident->id, 'user_id' => $tanod->id, 'status' => 'open']);
    $this->assertDatabaseHas('administrative_audits', ['user_id' => $tanod->id, 'action' => 'admin.incidents.store', 'record' => $incident->incident_number]);

    $this->withCookie(config('session.cookie'), session()->getId());
    for ($refresh = 0; $refresh < 3; $refresh++) {
        $this->get(route('staff.incidents.index', ['submitted' => 1]))->assertOk()->assertSee($incident->incident_number)->assertSee('Submit another report')
            ->assertDontSee($incident->details)->assertDontSee('data-incident-submission-form', false)
            ->assertDontSee('href="'.route('staff.incidents.show', $incident).'"', false);
    }
    $this->assertDatabaseCount('incidents', 1);
    $this->get(route('staff.incidents.index'))->assertSee('data-incident-submission-form', false)->assertDontSee($incident->incident_number)->assertSessionMissing('staff_incident_confirmation');
    $this->post(route('staff.incidents.store'), ordinaryTanodReport())->assertRedirect(route('staff.incidents.index', ['submitted' => 1]));
    $this->assertDatabaseCount('incidents', 2);
});

test('submission only account sees no operational shortcuts or restricted case controls', function () {
    $tanod = User::factory()->create(['role' => 'staff', 'staff_permissions' => ['incidents.submit']]);

    $response = $this->actingAs($tanod)->get(route('staff.incidents.index'))->assertOk()->assertSee('observed or reported facts')
        ->assertSee('designated authorized barangay personnel')->assertSee('cannot reliably identify every sensitive report')
        ->assertSee('name="purok"', false)->assertSee('name="immediate_action"', false)->assertSee('Reported theft')
        ->assertDontSee('name="is_sensitive"', false)->assertDontSee('Domestic Dispute')->assertDontSee('Add BPO record');
    $this->get(route('staff.incidents.create'))->assertRedirect(route('staff.incidents.index'));
    foreach (['staff.incidents.index', 'staff.dashboard', 'staff.demographics', 'staff.rfid-files.index', 'staff.protection-orders.index'] as $route) {
        if ($route !== 'staff.incidents.index') {
            $response->assertDontSee('href="'.route($route).'"', false);
        }
        $this->getJson(route($route))->assertForbidden();
    }
    $response->assertSee('Records')->assertSee('Incident Reports')->assertDontSee('>Submit incident</a>', false)
        ->assertDontSee('sidebar-label">Overview', false)->assertDontSee('sidebar-label">IoT Security', false);
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//nav//div[contains(@class, "sidebar-sec")][div[@class="sidebar-label" and normalize-space()="Records"]]/a[@href="'.route('staff.incidents.index').'" and @aria-current="page"]'))->toHaveCount(1);
    $this->get(route('staff.incidents.index', ['submitted' => 1]))->assertRedirect(route('staff.incidents.index'));
});

test('tanod incident navigation stays in records on personal pages', function (string $route) {
    $tanod = User::factory()->create(['role' => 'staff', 'staff_permissions' => ['incidents.submit']]);
    $response = $this->actingAs($tanod)->withSession(['auth.password_confirmed_at' => time()])->get(route($route))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);

    expect($xpath->query('//nav//div[contains(@class, "sidebar-sec")][div[@class="sidebar-label" and normalize-space()="Records"]]/a[@href="'.route('staff.incidents.index').'" and contains(., "Incident Reports")]'))->toHaveCount(1);
    $response->assertDontSee('>Submit incident</a>', false)->assertDontSee('sidebar-label">Overview', false)
        ->assertDontSee('sidebar-label">IoT Security', false);
})->with(['profile.edit', 'security.edit']);

test('incident reviewers keep the existing incident list instead of the submission only form', function () {
    $reviewer = User::factory()->create(['role' => 'staff', 'staff_permissions' => ['incidents.view', 'incidents.submit']]);
    $incident = Incident::factory()->create(['is_sensitive' => false, 'incident_type' => 'Noise Complaint']);

    $this->actingAs($reviewer)->get(route('staff.incidents.index'))->assertOk()->assertViewIs('admin.dashboard')
        ->assertViewHas('activeScreen', 'incidents')->assertDontSee('data-incident-submission-form', false);
    $this->getJson(route('staff.incidents.index'))->assertOk()->assertJsonPath('data.0.id', $incident->id);
});

test('submission only account cannot read its own report evidence or change its case', function () {
    Storage::fake('local');
    Storage::disk('local')->put('incident-attachments/evidence.pdf', 'private evidence');
    $tanod = User::factory()->create(['role' => 'staff', 'staff_permissions' => ['incidents.submit']]);
    $incident = Incident::factory()->create(['reported_by' => $tanod->id, 'attachments' => [['path' => 'incident-attachments/evidence.pdf', 'name' => 'evidence.pdf', 'mime' => 'application/pdf', 'size' => 16]]]);

    $this->actingAs($tanod)->get(route('staff.incidents.show', $incident))->assertForbidden();
    $this->get(route('staff.incidents.attachments.show', [$incident, 0]))->assertForbidden()->assertDontSee('private evidence');
    $this->patchJson(route('staff.incidents.update', $incident), ordinaryTanodReport(['status' => 'resolved', 'resolution_notes' => 'A forged case resolution.']))->assertForbidden();
    $this->deleteJson(route('staff.incidents.destroy', $incident))->assertForbidden();
    $this->assertDatabaseHas('incidents', ['id' => $incident->id, 'deleted_at' => null, 'status' => $incident->status]);
});

test('confirmation references are bound to the authenticated submitter', function () {
    $tanod = User::factory()->create(['role' => 'staff', 'staff_permissions' => ['incidents.submit']]);
    $other = User::factory()->create(['role' => 'staff', 'staff_permissions' => ['incidents.submit']]);
    $this->actingAs($other)->withSession(['staff_incident_confirmation' => ['actor_id' => $tanod->id, 'reference' => 'INC-PRIVATE-REFERENCE']])
        ->get(route('staff.incidents.index', ['submitted' => 1]))->assertRedirect(route('staff.incidents.index'))->assertDontSee('INC-PRIVATE-REFERENCE');
});

test('submission rejects forged administrative and actor fields without persisting a report', function (string $field, mixed $value) {
    Storage::fake('local');
    $tanod = User::factory()->create(['role' => 'staff', 'staff_permissions' => ['incidents.submit']]);

    $this->actingAs($tanod)->postJson(route('staff.incidents.store'), ordinaryTanodReport([$field => $value]))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('incidents', 0);
    $this->assertDatabaseCount('administrative_audits', 0);
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
})->with([
    ['reported_by', 999], ['updated_by', 999], ['actor_id', 999], ['user_id', 999],
    ['status', 'resolved'], ['resolution_notes', 'Already resolved'], ['resolved_at', '2026-10-09'],
    ['approved_by', 999], ['approved', true], ['approval_status', 'approved'],
    ['assigned_to', 999], ['remarks', 'Administrative decision'], ['complainant_resident_id', 999],
    ['respondent_resident_id', 999], ['is_sensitive', true], ['protection_order_id', 999],
    ['bpo_id', 999], ['request_restrictions', [['status' => 'active']]],
    ['staff_permissions', ['vawc.view']], ['role', 'admin'], ['incident_number', 'FORGED'],
]);

test('ordinary submission cannot become a restricted case through a custom category', function () {
    $tanod = User::factory()->create(['role' => 'staff', 'staff_permissions' => ['incidents.submit']]);
    $this->actingAs($tanod)->postJson(route('staff.incidents.store'), ordinaryTanodReport(['incident_type_selection' => 'Iba pa', 'incident_type' => 'VAWC / BPO']))->assertForbidden();
    $this->assertDatabaseCount('incidents', 0);
});

test('revoking submission access blocks the form confirmation and posting on the next request', function () {
    $tanod = User::factory()->create(['role' => 'staff', 'staff_permissions' => ['incidents.submit']]);
    $this->actingAs($tanod)->postJson(route('staff.incidents.store'), ordinaryTanodReport())->assertCreated()->assertJsonStructure(['reference', 'message'])->assertJsonMissingPath('incident');
    $super = User::factory()->superAdmin()->create();
    $this->actingAs($super)->patchJson(route('admin.users.update', $tanod), ['name' => $tanod->name, 'email' => $tanod->email, 'role' => 'staff', 'is_active' => true, 'staff_permissions' => []])->assertOk();
    $this->actingAs($tanod->fresh())->get(route('staff.incidents.index'))->assertForbidden();
    $this->get(route('staff.incidents.index', ['submitted' => 1]))->assertForbidden();
    $this->postJson(route('staff.incidents.store'), ordinaryTanodReport())->assertForbidden();
    $this->assertDatabaseCount('incidents', 1);
});

test('additional explicit permissions extend a tanod account and actor always comes from its session', function () {
    $staff = User::factory()->create(['role' => 'staff', 'staff_permissions' => ['incidents.submit', 'incidents.view', 'incidents.update']]);
    $this->actingAs($staff)->postJson(route('staff.incidents.store'), ordinaryTanodReport())->assertCreated()->assertJsonPath('incident.reported_by', $staff->id);
    $incident = Incident::query()->sole();
    $this->getJson(route('staff.incidents.show', $incident))->assertOk();
    $this->patchJson(route('staff.incidents.update', $incident), ordinaryTanodReport(['incident_type' => 'Noise Complaint', 'status' => 'under_review']))->assertOk();
});

test('authorized reclassification immediately removes ordinary access including summaries and evidence', function () {
    Storage::fake('local');
    Storage::disk('local')->put('incident-attachments/sensitive.pdf', 'confidential evidence');
    $incident = Incident::factory()->create(['incident_type' => 'Noise Complaint', 'status' => 'open', 'severity' => 'high', 'attachments' => [['path' => 'incident-attachments/sensitive.pdf', 'name' => 'evidence.pdf', 'mime' => 'application/pdf', 'size' => 21]]]);
    $ordinary = User::factory()->create(['role' => 'staff', 'staff_permissions' => ['incidents.view', 'incidents.update', 'dashboard.view']]);
    $reviewer = User::factory()->create(['role' => 'staff', 'staff_permissions' => StaffPermissions::presets()['Authorized Kagawad']]);
    $this->actingAs($ordinary)->getJson(route('staff.incidents.index'))->assertJsonPath('total', 1)->assertJsonPath('summary.high', 1);

    $this->actingAs($reviewer)->patchJson(route('staff.incidents.update', $incident), ordinaryTanodReport(['incident_type' => 'Noise Complaint', 'status' => 'under_review', 'is_sensitive' => true]))->assertOk()->assertJsonPath('incident.is_sensitive', true);
    $this->assertDatabaseHas('incident_events', ['incident_id' => $incident->id, 'user_id' => $reviewer->id, 'status' => 'under_review']);
    $this->actingAs($ordinary)->getJson(route('staff.incidents.index'))->assertJsonPath('total', 0)->assertJsonPath('summary.high', 0)->assertJsonPath('summary.pending', 0);
    $this->getJson(route('staff.dashboard.summary'))->assertOk()->assertDontSee($incident->incident_number);
    $this->getJson(route('staff.incidents.show', $incident))->assertForbidden();
    $this->get(route('staff.incidents.attachments.show', [$incident, 0]))->assertForbidden()->assertDontSee('confidential evidence');
    $this->patchJson(route('staff.incidents.update', $incident), ordinaryTanodReport(['incident_type' => 'Noise Complaint', 'status' => 'resolved']))->assertForbidden();

    $vawc = User::factory()->create(['role' => 'staff', 'staff_permissions' => ['vawc.view']]);
    $this->actingAs($vawc)->getJson(route('staff.incidents.show', $incident))->assertOk();
    $this->get(route('staff.incidents.attachments.show', [$incident, 0]))->assertOk();
    $this->actingAs(User::factory()->superAdmin()->create())->getJson(route('staff.incidents.show', $incident))->assertOk();
    $this->assertDatabaseCount('resident_request_restrictions', 0);
});

test('ordinary reporting creates no request restriction or automatic certificate block', function () {
    $residentAccount = User::factory()->resident()->create();
    $tanod = User::factory()->create(['role' => 'staff', 'staff_permissions' => ['incidents.submit']]);
    $this->actingAs($tanod)->postJson(route('staff.incidents.store'), ordinaryTanodReport(['respondent_name' => $residentAccount->name]))->assertCreated();
    expect(ResidentRequestRestriction::query()->count())->toBe(0);
    $this->actingAs($residentAccount, 'resident')->postJson(route('portal.request.store'), ['document_type' => 'Certificate of Residency', 'purpose' => 'Employment'])->assertOk();
});

test('combined narrative length and malformed optional values are rejected safely', function () {
    $tanod = User::factory()->create(['role' => 'staff', 'staff_permissions' => ['incidents.submit']]);
    $this->actingAs($tanod)->postJson(route('staff.incidents.store'), ordinaryTanodReport(['details' => str_repeat('x', 4990), 'immediate_action' => 'A reported immediate action.']))
        ->assertUnprocessable()->assertJsonValidationErrors(['details' => 'The description, purok and immediate action together must not exceed 5,000 characters.']);
    $this->postJson(route('staff.incidents.store'), ordinaryTanodReport(['purok' => ['not a string'], 'incident_type' => ['not a string'], 'incident_type_selection' => 'Iba pa']))->assertUnprocessable()->assertJsonValidationErrors(['purok', 'incident_type']);
    $this->assertDatabaseCount('incidents', 0);
});

test('submission only reports require an incident time rather than silently recording midnight', function () {
    $tanod = User::factory()->create(['role' => 'staff', 'staff_permissions' => ['incidents.submit']]);
    $this->actingAs($tanod)->postJson(route('staff.incidents.store'), ordinaryTanodReport(['occurred_time' => null]))
        ->assertUnprocessable()->assertJsonValidationErrors(['occurred_time' => 'Please enter the incident time.']);
    $this->assertDatabaseCount('incidents', 0);
});

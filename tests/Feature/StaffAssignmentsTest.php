<?php

use App\Models\BarangayProtectionOrder;
use App\Models\DocumentRequest;
use App\Models\EmployeeCabinetAccess;
use App\Models\Household;
use App\Models\Incident;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use App\StaffPermissions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

function assignedStaff(array $permissions): User
{
    return User::factory()->create(['role' => 'staff', 'staff_permissions' => $permissions]);
}

function assignmentPayload(User $user, array $permissions): array
{
    return ['name' => $user->name, 'email' => $user->email, 'role' => 'staff', 'is_active' => true, 'staff_permissions' => $permissions];
}

function submissionPayload(array $overrides = []): array
{
    return ['incident_type' => 'Noise Complaint', 'occurred_date' => today()->toDateString(), 'occurred_time' => '10:30', 'location' => 'Purok 1', 'severity' => 'low', 'details' => 'An ordinary incident reported by the patrol.', ...$overrides];
}

test('superadmin assigns editable presets and records actor target and before after', function () {
    $super = User::factory()->superAdmin()->create();
    $staff = assignedStaff([]);
    $permissions = StaffPermissions::presets()['Secretary / Assistant'];
    $permissions = array_values(array_diff($permissions, ['records.archive']));
    $this->actingAs($super)->patchJson(route('admin.users.update', $staff), assignmentPayload($staff, $permissions))->assertOk();
    expect($staff->fresh()->staff_permissions)->toEqualCanonicalizing($permissions);
    $audit = DB::table('administrative_audits')->where('action', 'admin.users.permissions-changed')->sole();
    expect($audit->user_id)->toBe($super->id)->and($audit->target_user_id)->toBe($staff->id);
    expect(json_decode($audit->before_assignments, true))->toBe([]);
    expect(json_decode($audit->after_assignments, true))->toEqualCanonicalizing($permissions);
    $this->get(route('admin.users.index'))->assertOk()->assertSee('Staff access assignments')->assertSee('Tanod');
});

test('staff cannot assign their own or another account permissions', function () {
    $staff = assignedStaff(StaffPermissions::keys());
    $target = assignedStaff([]);
    foreach ([$staff, $target] as $user) {
        $this->actingAs($staff)->patchJson(route('admin.users.update', $user), assignmentPayload($user, ['vawc.view']))->assertForbidden();
    }
    $this->assertDatabaseMissing('administrative_audits', ['action' => 'admin.users.permissions-changed']);
    expect($target->fresh()->staff_permissions)->toBe([]);
});

test('assignment validation rejects unknown administration and actions without page access', function (array $permissions) {
    $staff = assignedStaff([]);
    $this->actingAs(User::factory()->superAdmin()->create())->patchJson(route('admin.users.update', $staff), assignmentPayload($staff, $permissions))->assertUnprocessable();
    expect($staff->fresh()->staff_permissions)->toBe([]);
})->with([[['users.view']], [['records.update']], [['vawc.update']], [['incidents.view', 'incidents.view']]]);

test('unassigned staff and legacy nonprivileged admins reach access pending without loops', function (string $role) {
    $user = User::factory()->create(['role' => $role]);
    $this->post('/staff/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('staff.access-pending'));
    $this->get(route('staff.access-pending'))->assertOk()->assertSee('Access has not been assigned');
    $this->get(route('staff.dashboard'))->assertRedirect(route('staff.access-pending'));
    $this->getJson(route('staff.dashboard.summary'))->assertForbidden();
    expect($user->fresh()->staff_permissions)->toBeNull();
})->with(['staff', 'admin']);

test('staff page assignment allows its page but denies data and actions in other modules', function () {
    $staff = assignedStaff(['records.view']);
    $resident = Resident::factory()->create();
    $this->actingAs($staff)->get(route('staff.residents.index'))->assertOk();
    $this->getJson(route('staff.residents.index'))->assertOk()->assertJsonPath('total', 1)->assertJsonMissingPath('data.0.document_requests_count');
    $this->getJson(route('staff.residents.show', $resident))->assertOk()->assertJsonMissingPath('document_requests');
    $this->patchJson(route('staff.residents.update', $resident), [])->assertForbidden();
    $this->deleteJson(route('staff.residents.destroy', $resident))->assertForbidden();
    $this->getJson(route('staff.residents.export'))->assertForbidden();
    $this->postJson(route('staff.resident-profiling-imports.preview'), [])->assertForbidden();
    $this->getJson(route('staff.document-requests.live'))->assertForbidden();
    $this->getJson(route('legacy.staff.document-requests.live'))->assertForbidden();
    $this->get(route('staff.voters'))->assertForbidden();
    $this->get(route('staff.incidents.index'))->assertForbidden();
});

test('demographics browsing works without record editing or full registry access', function () {
    $purok = Purok::factory()->create();
    $household = Household::factory()->create(['purok_id' => $purok->id]);
    $resident = Resident::factory()->create(['household_id' => $household->id, 'purok' => $purok->name]);
    DocumentRequest::query()->create(['reference_code' => 'REQ-DEMOGRAPHICS', 'document_type' => 'Barangay Clearance', 'full_name' => $resident->full_name, 'address' => 'Anabu', 'status' => 'pending', 'resident_id' => $resident->id]);
    $this->actingAs(assignedStaff(['demographics.view']))->get(route('staff.demographics'))->assertOk();
    $this->getJson(route('staff.puroks.index'))->assertOk()->assertJsonStructure(['data', 'demographics']);
    $this->getJson(route('staff.households.index', ['purok_id' => $purok->id, 'search' => $household->household_number, 'per_page' => 1]))->assertOk()->assertJsonPath('total', 1);
    $this->getJson(route('staff.households.show', $household))->assertOk()->assertJsonPath('members.0.id', $resident->id);
    $this->getJson(route('staff.residents.show', $resident))->assertOk()->assertJsonPath('full_name', $resident->full_name)->assertJsonMissingPath('portal_account')->assertJsonMissingPath('document_requests')->assertJsonMissingPath('contact_number');
    $this->getJson(route('staff.residents.index'))->assertForbidden();
    $this->patchJson(route('staff.households.update', $household), [])->assertForbidden();
    $this->deleteJson(route('staff.households.members.destroy', [$household, $resident]))->assertForbidden();
    $this->postJson(route('staff.residents.store'), [])->assertForbidden();
});

test('tanod logs in to submission form and receives only a reference', function () {
    $tanod = assignedStaff(StaffPermissions::presets()['Tanod']);
    $this->post('/staff/login', ['email' => $tanod->email, 'password' => 'password'])->assertRedirect(route('staff.incidents.index'));
    $this->get(route('staff.incidents.index'))->assertOk()->assertSee('Submit incident report')->assertDontSee('href="'.route('staff.dashboard').'"', false);
    $response = $this->postJson(route('staff.incidents.store'), submissionPayload())->assertCreated()->assertJsonStructure(['message', 'reference'])->assertJsonMissingPath('incident');
    $incident = Incident::query()->sole();
    expect($response->json('reference'))->toBe($incident->incident_number);
    foreach (['staff.incidents.index', 'staff.incidents.options', 'staff.demographics', 'staff.rfid-files.index', 'staff.case-residents', 'staff.protection-orders.index'] as $route) {
        $this->getJson(route($route))->assertForbidden();
    }
    $this->getJson(route('staff.incidents.show', $incident))->assertForbidden();
    $this->patchJson(route('staff.incidents.update', $incident), submissionPayload(['status' => 'closed']))->assertForbidden();
    $this->deleteJson(route('staff.incidents.destroy', $incident))->assertForbidden();
    $this->postJson(route('staff.incidents.store'), submissionPayload(['is_sensitive' => true]))->assertUnprocessable()->assertJsonValidationErrors('is_sensitive');
    $this->postJson(route('staff.incidents.store'), submissionPayload(['incident_type' => 'VAWC']))->assertForbidden();
    $this->postJson(route('staff.incidents.store'), submissionPayload(['assigned_to' => $tanod->id]))->assertUnprocessable();
});

test('native tanod form shows a confirmation reference after submission', function () {
    $this->actingAs(assignedStaff(['incidents.submit']))->post(route('staff.incidents.store'), submissionPayload(['incident_type' => 'Theft']))->assertRedirect(route('staff.incidents.index', ['submitted' => 1]))->assertSessionHas('staff_incident_confirmation');
    $this->assertDatabaseHas('incidents', ['incident_type' => 'Theft']);
    $this->withCookie(config('session.cookie'), session()->getId())->get(route('staff.incidents.index', ['submitted' => 1]))->assertOk()->assertSee('Report received. Reference:');
});

test('tanod can select existing incident types and keeps the choice after validation fails', function () {
    $this->actingAs(assignedStaff(StaffPermissions::presets()['Tanod']));
    $response = $this->get(route('staff.incidents.index'))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $options = $xpath->query('//select[@name="incident_type_selection"]/option');

    expect(array_map(fn (DOMElement $option): string => $option->getAttribute('value'), iterator_to_array($options)))
        ->toBe(['', 'Disturbance / altercation', 'Noise Complaint', 'Theft', 'Vandalism', 'Accident', 'Suspicious activity', 'Property Dispute', 'Physical Assault', 'Iba pa']);
    expect($xpath->query('//select[@name="incident_type_selection" and @required]'))->toHaveCount(1);

    $this->from(route('staff.incidents.index'))->post(route('staff.incidents.store'), submissionPayload(['incident_type' => 'Property Dispute', 'details' => '']))
        ->assertRedirect(route('staff.incidents.index'))->assertSessionHasErrors('details');
    $document = new DOMDocument;
    @$document->loadHTML($this->get(route('staff.incidents.index'))->assertOk()->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->evaluate('string(//select[@name="incident_type_selection"]/option[@selected]/@value)'))->toBe('Property Dispute');
});

test('tanod stores a selected preset or the specified other incident type', function (string $selection, string $custom, string $stored) {
    $this->actingAs(assignedStaff(['incidents.submit']))->post(route('staff.incidents.store'), submissionPayload([
        'incident_type_selection' => $selection, 'incident_type' => $custom,
    ]))->assertRedirect(route('staff.incidents.index', ['submitted' => 1]))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('incidents', ['incident_type' => $stored]);
})->with([
    'preset' => ['Theft', 'Ignored custom draft', 'Theft'],
    'other' => ['Iba pa', '  Traffic obstruction  ', 'Traffic obstruction'],
]);

test('tanod must specify an incident type for Iba pa and retains the custom value on errors', function () {
    $this->actingAs(assignedStaff(['incidents.submit']))->postJson(route('staff.incidents.store'), submissionPayload([
        'incident_type_selection' => 'Iba pa', 'incident_type' => '   ',
    ]))->assertUnprocessable()->assertJsonValidationErrors(['incident_type' => 'Please specify the incident type.']);

    $this->from(route('staff.incidents.index'))->post(route('staff.incidents.store'), submissionPayload([
        'incident_type_selection' => 'Iba pa', 'incident_type' => 'Traffic obstruction', 'details' => '',
    ]))->assertRedirect(route('staff.incidents.index'))->assertSessionHasErrors('details');
    $this->withCookie(config('session.cookie'), session()->getId())->get(route('staff.incidents.index'))
        ->assertSee('value="Iba pa" selected', false)->assertSee('value="Traffic obstruction"', false);
    $this->assertDatabaseCount('incidents', 0);
});

test('custom incident types cannot bypass tanod restricted case protection', function () {
    $this->actingAs(assignedStaff(['incidents.submit']))->postJson(route('staff.incidents.store'), submissionPayload([
        'incident_type_selection' => 'Iba pa', 'incident_type' => 'VAWC',
    ]))->assertForbidden();
    $this->assertDatabaseCount('incidents', 0);
});

test('tanod can submit each supported attachment type', function (string $extension, string $mime) {
    Storage::fake('local');
    $attachment = in_array($extension, ['jpg', 'png'], true)
        ? UploadedFile::fake()->image('incident.'.$extension)
        : UploadedFile::fake()->create('incident.'.$extension, 20, $mime);

    $this->actingAs(assignedStaff(['incidents.submit']))->post(route('staff.incidents.store'), submissionPayload(['attachments' => [$attachment]]))
        ->assertRedirect(route('staff.incidents.index', ['submitted' => 1]))->assertSessionHasNoErrors()->assertSessionHas('staff_incident_confirmation');

    $stored = Incident::query()->sole()->attachments[0];
    expect($stored['name'])->toBe('incident.'.$extension);
    Storage::disk('local')->assertExists($stored['path']);
})->with([
    'JPEG' => ['jpg', 'image/jpeg'],
    'PNG' => ['png', 'image/png'],
    'WebP' => ['webp', 'image/webp'],
    'PDF' => ['pdf', 'application/pdf'],
]);

test('tanod sees a clear attachment error and keeps report fields after an invalid upload', function () {
    Storage::fake('local');
    $textFile = UploadedFile::fake()->createWithContent('text.txt', 'This is a text file, not an image.');
    $invalid = new UploadedFile($textFile->getPathname(), 'disguised.jpg', null, null, true);
    $this->actingAs(assignedStaff(['incidents.submit']))->from(route('staff.incidents.index'))
        ->post(route('staff.incidents.store'), submissionPayload(['attachments' => [$invalid], 'severity' => 'high']))
        ->assertRedirect(route('staff.incidents.index'))->assertSessionHasErrors([
            'attachments.0' => 'Attachment 1 must be a JPG, PNG, WebP image or PDF file. Other file types are not supported.',
        ]);

    $this->withCookie(config('session.cookie'), session()->getId())->get(route('staff.incidents.index'))->assertOk()->assertSee('Attachment 1 must be a JPG, PNG, WebP image or PDF file.')
        ->assertSee('id="incident-attachment-error"', false)->assertSee('aria-invalid="true"', false)
        ->assertSee('Select the attachments again before resubmitting.')
        ->assertSee('value="high" selected', false)->assertSee('An ordinary incident reported by the patrol.');
    $this->assertDatabaseCount('incidents', 0);
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('tanod attachment limits have understandable error messages', function (string $scenario, string $key, string $message) {
    $files = $scenario === 'size'
        ? [UploadedFile::fake()->create('large.jpg', 5121, 'image/jpeg')]
        : array_map(fn (): UploadedFile => UploadedFile::fake()->image('incident.jpg'), range(1, 6));

    $this->actingAs(assignedStaff(['incidents.submit']))->postJson(route('staff.incidents.store'), submissionPayload(['attachments' => $files]))
        ->assertUnprocessable()->assertJsonValidationErrors([$key => $message]);
    $this->assertDatabaseCount('incidents', 0);
})->with([
    'oversize' => ['size', 'attachments.0', 'Attachment 1 is too large. Each file must be 5 MB or smaller.'],
    'too many' => ['count', 'attachments', 'You can attach up to five files per report.'],
]);

test('ordinary incidents and VAWC are isolated in lists counts direct URLs and private files', function () {
    Storage::fake('local');
    Storage::disk('local')->put('private/case.pdf', 'confidential');
    $ordinary = Incident::factory()->create(['incident_type' => 'Noise Complaint']);
    $sensitive = Incident::factory()->create(['is_sensitive' => true, 'details' => 'VAWC confidential narrative', 'attachments' => [['path' => 'private/case.pdf', 'name' => 'case.pdf', 'mime' => 'application/pdf', 'size' => 12]]]);
    $bpo = BarangayProtectionOrder::factory()->create();
    $staff = assignedStaff(['incidents.view', 'incidents.update', 'dashboard.view']);
    $this->actingAs($staff)->getJson(route('staff.incidents.index'))->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $ordinary->id)->assertDontSee('VAWC confidential narrative');
    $this->getJson(route('staff.incidents.show', $sensitive))->assertForbidden();
    $this->getJson(route('staff.incidents.attachments.show', [$sensitive, 0]))->assertForbidden();
    $this->patchJson(route('staff.incidents.update', $sensitive), submissionPayload(['status' => 'closed']))->assertForbidden();
    $this->patchJson(route('staff.incidents.update', $ordinary), submissionPayload(['status' => 'closed', 'is_sensitive' => true]))->assertForbidden();
    $this->getJson(route('staff.protection-orders.show', $bpo))->assertForbidden();
    $this->getJson(route('staff.dashboard.summary'))->assertOk()->assertJsonPath('summary.issued_certificates', null)->assertDontSee('VAWC confidential narrative');
    $this->actingAs(assignedStaff(['vawc.view']))->getJson(route('staff.incidents.index'))->assertOk()->assertJsonPath('total', 2);
    $this->getJson(route('staff.incidents.show', $ordinary))->assertForbidden();
    $this->getJson(route('staff.protection-orders.show', $bpo))->assertOk();
    $this->getJson(route('staff.incidents.attachments.show', [$sensitive, 0]))->assertOk();
});

test('authorized kagawad manages restricted cases and BPO without administration access', function () {
    $kagawad = assignedStaff(StaffPermissions::presets()['Authorized Kagawad']);
    $this->actingAs($kagawad)->postJson(route('staff.incidents.store'), submissionPayload(['is_sensitive' => true]))->assertCreated();
    $incident = Incident::query()->sole();
    $this->postJson(route('staff.protection-orders.store'), ['incident_id' => $incident->id, 'protected_person' => 'Protected person', 'respondent' => 'Respondent', 'issued_on' => today()->toDateString(), 'status' => 'recorded', 'issuing_authority' => 'Barangay authority'])->assertCreated();
    $order = BarangayProtectionOrder::query()->sole();
    $this->patchJson(route('staff.protection-orders.update', $order), ['incident_id' => $incident->id, 'protected_person' => 'Protected person', 'respondent' => 'Respondent', 'issued_on' => today()->toDateString(), 'status' => 'ended', 'issuing_authority' => 'Barangay authority'])->assertOk();
    $this->getJson(route('admin.users.index'))->assertForbidden();
    $this->get(route('admin.smart-cabinet.index'))->assertForbidden();
    $this->get(route('admin.cabinet-access.index'))->assertForbidden();
    $this->getJson(route('admin.audit.index'))->assertForbidden();
    $this->get(route('admin.settings'))->assertForbidden();
});

test('document viewers cannot process issue print or fetch files without module access', function () {
    $request = DocumentRequest::query()->create(['reference_code' => 'REQ-PERMISSIONS', 'document_type' => 'Barangay Clearance', 'full_name' => 'Test Resident', 'address' => 'Anabu', 'status' => 'pending']);
    $this->actingAs(assignedStaff(['documents.view']))->getJson(route('staff.document-requests.live'))->assertOk();
    $this->patchJson(route('staff.document-requests.update-status', $request), ['status' => 'processing'])->assertForbidden();
    $this->postJson(route('staff.document-requests.issue', $request))->assertForbidden();
    $this->postJson(route('staff.issued-certificates.store'), [])->assertForbidden();
    $this->actingAs(assignedStaff(['dashboard.view']))->getJson(route('staff.document-requests.attachment', $request))->assertForbidden();
    $this->get(route('staff.dashboard'))->assertOk()->assertDontSee($request->reference_code);
});

test('revocation takes effect on the next request without changing physical cabinet permissions', function () {
    $staff = assignedStaff(['records.view', 'rfid.view']);
    $access = EmployeeCabinetAccess::factory()->create(['user_id' => $staff->id, 'is_active' => true, 'rfid_enrollment_status' => 'enrolled', 'face_enrollment_status' => 'enrolled']);
    expect($access->isEffective())->toBeTrue();
    $this->actingAs($staff)->getJson(route('staff.residents.index'))->assertOk();
    $super = User::factory()->superAdmin()->create();
    $this->actingAs($super)->patchJson(route('admin.users.update', $staff), assignmentPayload($staff, []))->assertOk();
    $this->actingAs($staff->fresh())->getJson(route('staff.residents.index'))->assertForbidden();
    $this->get(route('staff.rfid-files.index'))->assertForbidden();
    expect($staff->fresh()->cabinetAccess->is_active)->toBeTrue();
    expect($access->fresh()->isEffective())->toBeTrue();
    $this->get(route('staff.dashboard'))->assertRedirect(route('staff.access-pending'));
});

test('resident accounts cannot inherit assignments even if stored permission data exists', function () {
    $resident = User::factory()->resident()->create(['staff_permissions' => StaffPermissions::keys()]);
    expect($resident->hasPermission('records.view'))->toBeFalse();
    $this->actingAs($resident, 'web')->getJson(route('staff.residents.index'))->assertForbidden();
    $this->postJson(route('staff.incidents.store'), submissionPayload())->assertForbidden();
    $this->patchJson(route('admin.users.update', $resident), assignmentPayload($resident, ['records.view']))->assertForbidden();
    $this->actingAs(User::factory()->superAdmin()->create())->patchJson(route('admin.users.update', $resident), assignmentPayload($resident, ['records.view']))->assertForbidden();
});

test('superadmin retains every module without explicit assignments', function () {
    $super = User::factory()->superAdmin()->create();
    foreach (StaffPermissions::keys() as $permission) {
        expect($super->hasPermission($permission))->toBeTrue();
    }
    $this->actingAs($super);
    foreach (['admin.dashboard', 'staff.demographics', 'staff.residents.index', 'staff.voters', 'staff.document-requests.index', 'staff.request-eligibility', 'staff.incidents.index', 'staff.rfid-files.index', 'admin.users.index', 'admin.settings', 'admin.audit'] as $name) {
        $this->get(route($name))->assertOk();
    }
});

test('staff creation saves explicit assignments without changing the three main levels', function () {
    $super = User::factory()->superAdmin()->create();
    $response = $this->actingAs($super)->postJson(route('admin.users.store'), ['name' => 'Patrol Staff', 'email' => 'patrol@example.test', 'password' => 'SecureStaffPassword123!', 'role' => 'staff', 'is_active' => true, 'staff_permissions' => ['incidents.submit'], 'is_super_admin' => true])->assertCreated();
    $staff = User::query()->findOrFail($response->json('id'));
    expect($staff->role)->toBe('staff')->and($staff->is_super_admin)->toBeFalse()->and($staff->staff_permissions)->toBe(['incidents.submit']);
    $this->assertDatabaseHas('administrative_audits', ['action' => 'admin.users.permissions-changed', 'target_user_id' => $staff->id, 'user_id' => $super->id]);
});

test('module actions permit ordinary case management only when explicitly assigned', function () {
    $viewer = assignedStaff(['incidents.view']);
    $incident = Incident::factory()->create(['incident_type' => 'Noise Complaint']);
    $this->actingAs($viewer)->getJson(route('staff.incidents.show', $incident))->assertOk()->assertJsonPath('can_update', false)->assertJsonPath('can_archive', false);
    $this->postJson(route('staff.incidents.store'), submissionPayload())->assertForbidden();
    $this->patchJson(route('staff.incidents.update', $incident), submissionPayload(['status' => 'closed']))->assertForbidden();
    $manager = assignedStaff(['incidents.view', 'incidents.update', 'incidents.archive']);
    $this->actingAs($manager)->patchJson(route('staff.incidents.update', $incident), submissionPayload(['status' => 'closed']))->assertOk();
    $this->deleteJson(route('staff.incidents.destroy', $incident))->assertOk();
    $this->assertSoftDeleted($incident);
});

test('household module and lookup endpoints do not expose the full resident registry', function () {
    $staff = assignedStaff(['households.view', 'households.create', 'households.update']);
    $resident = Resident::factory()->create(['contact_number' => '09171234567']);
    $this->actingAs($staff)->get(route('staff.households.page'))->assertOk();
    $this->getJson(route('staff.households.page'))->assertOk()->assertJsonMissingPath('data.0.contact_number');
    $this->getJson(route('staff.case-residents'))->assertOk()->assertJsonPath('data.0.id', $resident->id)->assertJsonMissingPath('data.0.contact_number')->assertJsonMissingPath('data.0.special_groups');
    $this->getJson(route('staff.residents.index'))->assertForbidden();
    $created = $this->postJson(route('staff.households.store'), ['address' => 'Anabu', 'household_name' => 'New Household'])->assertCreated();
    $household = Household::query()->findOrFail($created->json('household.id'));
    $this->patchJson(route('staff.households.members.update', [$household, $resident]), ['relationship_to_household_head' => 'Child'])->assertOk();
    $this->deleteJson(route('staff.households.members.destroy', [$household, $resident]))->assertForbidden();
    $this->patchJson(route('staff.residents.update', $resident), [])->assertForbidden();
});

test('document processing and issuance are distinct actions', function () {
    $request = DocumentRequest::query()->create(['reference_code' => 'REQ-DISTINCT', 'document_type' => 'Certificate of Residency', 'full_name' => 'Test Resident', 'address' => 'Anabu', 'status' => 'pending']);
    $processor = assignedStaff(['documents.view', 'documents.process']);
    $this->actingAs($processor)->patchJson(route('staff.document-requests.update-status', $request), ['status' => 'ready_for_release'])->assertOk();
    $this->postJson(route('staff.document-requests.issue', $request))->assertForbidden();
    $issuer = assignedStaff(['documents.view', 'documents.issue']);
    $this->actingAs($issuer)->patchJson(route('staff.document-requests.update-status', $request), ['status' => 'rejected', 'rejection_reason' => 'A long rejection reason.'])->assertForbidden();
    $this->postJson(route('staff.document-requests.issue', $request))->assertOk();
    $certificate = $request->issuedCertificate()->sole();
    $this->get(route('staff.issued-certificates.print', $certificate))->assertForbidden();
    $this->actingAs(assignedStaff(['documents.view', 'documents.print']))->get(route('staff.issued-certificates.print', $certificate))->assertOk();
});

test('user account form groups its controls and keeps save outside the scrolling body', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $response = $this->get(route('admin.users.index'))->assertOk();

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $body = '//div[@id="modal-adduser"]//div[contains(@class,"user-account-body")]';
    expect($xpath->query($body.'//input[@id="adduser-name"]')->length)->toBe(1);
    expect($xpath->query($body.'//select[@id="adduser-status"]')->length)->toBe(1);
    expect($xpath->query($body.'//input[@data-staff-permission]')->length)->toBe(count(StaffPermissions::keys()));
    expect($xpath->query($body.'//div[contains(@class,"staff-permission-group")]')->length)->toBe(3);
    expect($xpath->query($body.'//fieldset')->length)->toBe(count(StaffPermissions::modules()));
    expect($xpath->query($body.'//button[@id="adduser-save-btn"]')->length)->toBe(0);
    expect($xpath->query('//div[@id="modal-adduser"]//div[contains(@class,"modal-footer")]/button[@id="adduser-save-btn"]')->length)->toBe(1);
});

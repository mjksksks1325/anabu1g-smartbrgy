<?php

use App\Models\BarangayProtectionOrder;
use App\Models\Incident;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function phaseThreeIncident(array $overrides = []): array
{
    return ['incident_type' => 'Noise Complaint', 'occurred_date' => today()->toDateString(), 'location' => 'Purok 1', 'severity' => 'medium', 'details' => 'Private incident narrative for authorized case handling.', ...$overrides];
}
function phaseThreeOrder(Incident $incident, array $overrides = []): array
{
    return ['incident_id' => $incident->id, 'protected_person' => 'Confidential Protected Person', 'respondent' => 'Confidential Respondent', 'issued_on' => today()->toDateString(), 'status' => 'recorded', 'issuing_authority' => 'Verified barangay authority', 'internal_remarks' => 'Confidential BPO notes', ...$overrides];
}
it('records explicit resident links and external parties without creating duplicate residents', function () {
    $staff = User::factory()->assignedOperations()->create(['role' => 'staff']);
    $resident = Resident::factory()->create();
    $response = $this->actingAs($staff)->postJson(route('staff.incidents.store'), phaseThreeIncident(['complainant_resident_id' => $resident->id, 'complainant_name' => 'Incorrect supplied name', 'respondent_name' => 'External person', 'assigned_to' => $staff->id, 'remarks' => 'Internal remarks']));
    $response->assertCreated()->assertJsonPath('incident.status', 'open')->assertJsonPath('incident.complainant_name', $resident->full_name)->assertJsonPath('incident.respondent_name', 'External person')->assertJsonPath('incident.complainant_resident_id', $resident->id);
    $this->assertDatabaseCount('residents', 1);
    $this->assertDatabaseHas('incidents', ['reported_by' => $staff->id, 'updated_by' => $staff->id, 'assigned_to' => $staff->id]);
    $this->assertDatabaseHas('administrative_audits', ['action' => 'admin.incidents.store', 'user_id' => $staff->id, 'record' => Incident::query()->sole()->incident_number]);
    expect(DB::table('administrative_audits')->where('action', 'admin.incidents.store')->value('changed_fields'))->toContain('remarks')->not->toContain('Internal remarks', 'Private incident narrative');
    $this->patchJson(route('staff.incidents.update', Incident::query()->sole()), phaseThreeIncident(['status' => 'under_review', 'complainant_name' => 'Different external name']))
        ->assertOk()->assertJsonPath('incident.complainant_resident_id', $resident->id)->assertJsonPath('incident.complainant_name', $resident->full_name);
});
it('uses unique case identifiers with database uniqueness protection', function () {
    $incidents = Incident::factory()->count(20)->create();
    expect($incidents->pluck('incident_number')->unique())->toHaveCount(20);
    $duplicate = $incidents->first()->replicate();
    $duplicate->incident_number = $incidents->first()->incident_number;
    expect(fn () => $duplicate->save())->toThrow(QueryException::class);
});
it('denies incident access to resident viewer and inactive accounts', function (string $role, bool $active) {
    $user = User::factory()->assignedOperations()->create(['role' => $role, 'is_active' => $active]);
    $incident = Incident::factory()->create();
    $method = $active ? 'assertForbidden' : 'assertUnauthorized';
    $this->actingAs($user)->getJson(route('staff.incidents.index'))->$method();
    $this->getJson(route('staff.incidents.show', $incident))->$method();
    $this->postJson(route('staff.incidents.store'), phaseThreeIncident())->$method();
})->with([['resident', true], ['viewer', true], ['staff', false]]);
it('retains case status history and audits closing without putting narrative in general audit data', function () {
    $staff = User::factory()->assignedOperations()->create(['role' => 'staff']);
    $incident = Incident::factory()->create(['status' => 'open']);
    $this->actingAs($staff)->patchJson(route('staff.incidents.update', $incident), phaseThreeIncident(['status' => 'under_review']))->assertOk();
    $this->patchJson(route('staff.incidents.update', $incident), phaseThreeIncident(['status' => 'closed']))->assertOk();
    $this->getJson(route('staff.incidents.show', $incident))->assertJsonPath('history.0.previous_status', 'open')->assertJsonPath('history.0.status', 'under_review')->assertJsonPath('history.1.status', 'closed');
    $this->assertDatabaseHas('incidents', ['id' => $incident->id, 'updated_by' => $staff->id, 'status' => 'closed']);
    $this->assertDatabaseHas('administrative_audits', ['action' => 'admin.incidents.closed', 'user_id' => $staff->id]);
    expect(DB::table('administrative_audits')->get()->toJson())->not->toContain('Private incident narrative');
});
it('searches linked residents and filters cases by category dates assignment and status', function () {
    $staff = User::factory()->assignedOperations()->create();
    $resident = Resident::factory()->create(['last_name' => 'UniqueLinkedSurname']);
    $case = Incident::factory()->create(['complainant_resident_id' => $resident->id, 'complainant_name' => 'Old snapshot', 'status' => 'referred', 'assigned_to' => $staff->id, 'incident_type' => 'Property Dispute', 'occurred_at' => today()]);
    Incident::factory()->create(['status' => 'closed']);
    $this->actingAs($staff)->getJson(route('staff.incidents.index', ['search' => 'UniqueLinkedSurname', 'status' => 'referred', 'incident_type' => 'Property Dispute', 'date_from' => today()->toDateString(), 'date_to' => today()->toDateString(), 'assigned_to' => $staff->id, 'per_page' => 1]))->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $case->id);
    $this->getJson(route('staff.incidents.index', ['search' => $case->incident_number]))->assertJsonPath('total', 1);
});
it('rejects invalid resident links and assignment to non-staff accounts', function () {
    $staff = User::factory()->assignedOperations()->create();
    $residentAccount = User::factory()->resident()->create();
    $this->actingAs($staff)->postJson(route('staff.incidents.store'), phaseThreeIncident(['complainant_resident_id' => 999999, 'assigned_to' => $residentAccount->id]))->assertUnprocessable()->assertJsonValidationErrors(['complainant_resident_id', 'assigned_to']);
    $this->assertDatabaseEmpty('incidents');
});
it('allows only superadmin to access and manage restricted BPO records', function (string $role) {
    $user = User::factory()->assignedOperations()->create(['role' => $role]);
    $order = BarangayProtectionOrder::factory()->create();
    $this->actingAs($user)->getJson(route('staff.protection-orders.index'))->assertForbidden()->assertDontSee('Confidential');
    $this->getJson(route('staff.protection-orders.show', $order))->assertForbidden();
    $this->postJson(route('staff.protection-orders.store'), phaseThreeOrder($order->incident))->assertForbidden();
    $this->patchJson(route('staff.protection-orders.update', $order), phaseThreeOrder($order->incident))->assertForbidden();
})->with(['staff', 'admin', 'viewer', 'resident']);
it('records BPO resident references without inferred legal dates and audits updates privately', function () {
    $super = User::factory()->superAdmin()->create();
    $resident = Resident::factory()->create();
    $case = Incident::factory()->create(['complainant_resident_id' => $resident->id]);
    $this->actingAs($super)->postJson(route('staff.protection-orders.store'), phaseThreeOrder($case, ['protected_resident_id' => $resident->id]))->assertCreated()->assertJsonPath('order.protected_person', $resident->full_name)->assertJsonPath('order.ends_on', null);
    $order = BarangayProtectionOrder::query()->sole();
    $this->patchJson(route('staff.protection-orders.update', $order), phaseThreeOrder($case, ['status' => 'ended']))->assertOk()->assertJsonPath('order.protected_resident_id', $resident->id)->assertJsonPath('order.protected_person', $resident->full_name);
    $response = $this->getJson(route('staff.protection-orders.show', $order))->assertOk()->assertJsonPath('incident_id', $case->id)->assertHeader('Cache-Control');
    expect($response->headers->get('Cache-Control'))->toContain('no-store', 'private');
    $this->assertDatabaseHas('administrative_audits', ['action' => 'admin.protection-orders.status-changed', 'user_id' => $super->id]);
    $audit = $this->getJson(route('admin.audit.index'))->assertOk();
    $audit->assertDontSee('Confidential Protected Person')->assertDontSee('Confidential Respondent')->assertDontSee('Confidential BPO notes');
    $this->actingAs(User::factory()->assignedOperations()->create(['role' => 'staff']), 'web')->getJson(route('staff.incidents.show', $case))->assertDontSee('Confidential BPO notes')->assertDontSee('protected_person');
});
it('keeps sensitive controls out of unauthorized staff HTML', function () {
    $this->actingAs(User::factory()->assignedOperations()->create(['role' => 'staff']), 'web')->get(route('staff.incidents.index'))->assertOk()->assertDontSee('id="modal-protection-order"', false)->assertDontSee('id="modal-request-restriction"', false);
    $this->actingAs(User::factory()->superAdmin()->create())->get(route('staff.incidents.index'))->assertOk()->assertSee('id="modal-protection-order"', false)->assertSee('id="modal-request-restriction"', false);
});

it('requires authentication for BPO and restriction endpoints', function () {
    $order = BarangayProtectionOrder::factory()->create();
    $this->getJson(route('staff.protection-orders.index'))->assertUnauthorized();
    $this->getJson(route('staff.protection-orders.show', $order))->assertUnauthorized();
    $this->postJson(route('staff.protection-orders.store'), phaseThreeOrder($order->incident))->assertUnauthorized();
    $this->getJson(route('staff.request-restrictions.index'))->assertUnauthorized();
    $this->postJson(route('staff.request-restrictions.store'), [])->assertUnauthorized();
});

it('records an explicitly supplied BPO end date without requiring an effective date', function () {
    $case = Incident::factory()->create();
    $this->actingAs(User::factory()->superAdmin()->create())->postJson(route('staff.protection-orders.store'), phaseThreeOrder($case, ['ends_on' => today()->addDay()->toDateString()]))
        ->assertCreated()->assertJsonPath('order.effective_on', null)->assertJsonPath('order.ends_on', today()->addDay()->toDateString());
});

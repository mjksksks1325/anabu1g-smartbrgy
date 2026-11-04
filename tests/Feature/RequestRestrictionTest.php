<?php

use App\Actions\IssueCertificate;
use App\CertificateType;
use App\Exceptions\CertificateIssuanceException;
use App\Models\BarangayProtectionOrder;
use App\Models\Incident;
use App\Models\ResidentRequestRestriction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

function phaseThreeRestriction(int $residentId, array $overrides = []): array
{
    return ['resident_id' => $residentId, 'reason_category' => 'Reviewed case decision', 'internal_reason' => 'Confidential reviewed allegations and staff notes', 'starts_at' => now()->subMinute()->toDateTimeString(), ...$overrides];
}
function phaseThreeDocument(string $type = 'Barangay Clearance'): array
{
    return ['document_type' => $type, 'purpose' => 'Personal requirements'];
}
it('does not block resident requests merely because a linked incident or BPO exists', function () {
    $account = User::factory()->resident()->create();
    $incident = Incident::factory()->create(['status' => 'open', 'respondent_resident_id' => $account->resident_id]);
    BarangayProtectionOrder::factory()->create(['incident_id' => $incident->id, 'respondent_resident_id' => $account->resident_id]);
    $this->actingAs($account, 'resident')->postJson(route('portal.request.store'), phaseThreeDocument())->assertOk()->assertJsonPath('success', true);
    $this->assertDatabaseCount('document_requests', 1);
});
it('requires explicit authorized review before activating a restriction and preserves lifting history', function () {
    $account = User::factory()->resident()->create();
    $admin = User::factory()->superAdmin()->create(['role' => 'admin']);
    $case = Incident::factory()->create(['respondent_resident_id' => $account->resident_id]);
    $this->actingAs($admin, 'web')->postJson(route('staff.request-restrictions.store'), phaseThreeRestriction($account->resident_id, ['incident_id' => $case->id, 'affected_document_type' => 'Barangay Clearance']))->assertCreated()->assertJsonPath('restriction.status', 'pending_review')->assertJsonPath('restriction.reviewed_at', null);
    $restriction = ResidentRequestRestriction::query()->sole();
    $this->actingAs($account, 'resident')->postJson(route('portal.request.store'), phaseThreeDocument())->assertOk();
    $this->actingAs($admin, 'web')->postJson(route('staff.request-restrictions.review', $restriction))->assertOk()->assertJsonPath('restriction.reviewed_by', $admin->id);
    $response = $this->actingAs($account, 'resident')->postJson(route('portal.request.store'), phaseThreeDocument());
    $response->assertUnprocessable()->assertJsonValidationErrors('document_type')->assertSee(ResidentRequestRestriction::MESSAGE)->assertDontSee('Confidential reviewed allegations')->assertDontSee('Reviewed case decision');
    $this->postJson(route('portal.request.store'), phaseThreeDocument('Certificate of Residency'))->assertOk();
    $this->actingAs($admin, 'web')->postJson(route('staff.request-restrictions.lift', $restriction), ['lift_reason' => 'Decision lifted after review'])->assertOk();
    $this->actingAs($account, 'resident')->postJson(route('portal.request.store'), phaseThreeDocument())->assertOk();
    $this->assertDatabaseHas('resident_request_restrictions', ['id' => $restriction->id, 'status' => 'lifted', 'lifted_by' => $admin->id, 'lift_reason' => 'Decision lifted after review']);
    $this->assertDatabaseHas('administrative_audits', ['action' => 'admin.request-restrictions.reviewed', 'user_id' => $admin->id]);
    $this->assertDatabaseHas('administrative_audits', ['action' => 'admin.request-restrictions.lifted', 'user_id' => $admin->id]);
    $this->assertDatabaseCount('document_requests', 3);
});
it('enforces all-document and type-specific reviewed restriction scopes', function (?string $scope, string $requested, bool $blocked) {
    $account = User::factory()->resident()->create();
    ResidentRequestRestriction::factory()->reviewed()->create(['resident_id' => $account->resident_id, 'affected_document_type' => $scope]);
    $response = $this->actingAs($account, 'resident')->postJson(route('portal.request.store'), phaseThreeDocument($requested));
    if ($blocked) {
        $response->assertUnprocessable()->assertSee(ResidentRequestRestriction::MESSAGE);
        $this->assertDatabaseEmpty('document_requests');
    } else {
        $response->assertOk();
        $this->assertDatabaseCount('document_requests', 1);
    }
})->with([[null, 'Barangay Clearance', true], [null, 'Certificate of Residency', true], ['Barangay Clearance', 'Barangay Clearance', true], ['Barangay Clearance', 'Certificate of Residency', false], ['Registered Voter Certification', 'Registered Voter Certification', true], ['Registered Voter Certification', 'Barangay Clearance', false]]);
it('does not block pending lifted expired future or unreviewed restrictions', function (array $attributes) {
    $account = User::factory()->resident()->create();
    ResidentRequestRestriction::factory()->reviewed()->create(['resident_id' => $account->resident_id, ...$attributes]);
    $this->actingAs($account, 'resident')->postJson(route('portal.request.store'), phaseThreeDocument())->assertOk();
    $this->assertDatabaseCount('document_requests', 1);
})->with(['pending' => [['status' => 'pending_review']], 'lifted' => [['status' => 'lifted']], 'expired' => [['status' => 'expired']], 'missing review' => [['reviewed_by' => null, 'reviewed_at' => null]], 'not started' => [fn () => ['starts_at' => now()->addDay()]], 'past end' => [fn () => ['ends_at' => now()->subSecond()]]]);
it('expires restrictions once and records an audit while retaining the historical row', function () {
    $account = User::factory()->resident()->create();
    $restriction = ResidentRequestRestriction::factory()->reviewed()->create(['resident_id' => $account->resident_id, 'ends_at' => now()->subMinute()]);
    $this->artisan('restrictions:expire')->assertSuccessful();
    $this->artisan('restrictions:expire')->assertSuccessful();
    expect($restriction->fresh()->status)->toBe('expired');
    expect(DB::table('administrative_audits')->where('action', 'admin.request-restrictions.expired')->count())->toBe(1);
    $this->assertDatabaseCount('resident_request_restrictions', 1);
});
it('denies restriction mutation to unauthorized roles', function (string $role) {
    $user = User::factory()->create(['role' => $role]);
    $restriction = ResidentRequestRestriction::factory()->create();
    $this->actingAs($user)->postJson(route('staff.request-restrictions.store'), phaseThreeRestriction($restriction->resident_id))->assertForbidden();
    $this->postJson(route('staff.request-restrictions.review', $restriction))->assertForbidden();
    $this->postJson(route('staff.request-restrictions.lift', $restriction), ['lift_reason' => 'Attempted unauthorized decision'])->assertForbidden();
    expect($restriction->fresh()->status)->toBe('pending_review');
})->with(['staff', 'viewer', 'resident']);
it('rejects forged review metadata unrelated cases and invalid document scope', function () {
    $account = User::factory()->resident()->create();
    $case = Incident::factory()->create();
    $this->actingAs(User::factory()->superAdmin()->create(['role' => 'admin']))->postJson(route('staff.request-restrictions.store'), phaseThreeRestriction($account->resident_id, ['status' => 'active', 'reviewed_by' => 1, 'affected_document_type' => 'Unsupported']))->assertUnprocessable()->assertJsonValidationErrors(['status', 'reviewed_by', 'affected_document_type']);
    $this->postJson(route('staff.request-restrictions.store'), phaseThreeRestriction($account->resident_id, ['incident_id' => $case->id]))->assertUnprocessable()->assertJsonValidationErrors('incident_id');
    $this->assertDatabaseEmpty('resident_request_restrictions');
});
it('prevents reactivation of lifted or already ended restrictions', function (string $condition) {
    $restriction = ResidentRequestRestriction::factory()->create($condition === 'lifted' ? ['status' => 'lifted'] : ['ends_at' => now()->subMinute()]);
    $this->actingAs(User::factory()->superAdmin()->create(['role' => 'admin']))->postJson(route('staff.request-restrictions.review', $restriction))->assertUnprocessable()->assertJsonValidationErrors('restriction');
})->with(['lifted', 'past end']);
it('enforces reviewed restrictions during certificate issuance without changing existing QR verification', function () {
    Storage::fake('public');
    Storage::fake('local');
    $account = User::factory()->resident()->create();
    $resident = $account->resident;
    $attributes = ['certificate_type' => CertificateType::CertificateOfResidency->value, 'resident_id' => $resident->id, 'resident_name' => $resident->full_name, 'address' => $resident->address];
    $issued = app(IssueCertificate::class)->handle($attributes, null, 'Authorized staff');
    ResidentRequestRestriction::factory()->reviewed()->create(['resident_id' => $resident->id, 'affected_document_type' => CertificateType::CertificateOfResidency->value]);
    expect(fn () => app(IssueCertificate::class)->handle($attributes, null, 'Authorized staff'))->toThrow(CertificateIssuanceException::class, ResidentRequestRestriction::MESSAGE);
    $this->get(route('certificate.verify', $issued->verification_code))->assertOk();
    $this->assertDatabaseCount('issued_certificates', 1);
});
it('explains a hold with the staff message and the dates staff set, without internal details or the case', function () {
    $this->travelTo('2026-11-04 09:00:00');
    $account = User::factory()->resident()->create();
    $admin = User::factory()->superAdmin()->create(['role' => 'admin']);
    $case = Incident::factory()->create(['respondent_resident_id' => $account->resident_id, 'details' => 'Secret case allegation']);
    $this->actingAs($admin, 'web')->postJson(route('staff.request-restrictions.store'), phaseThreeRestriction($account->resident_id, [
        'incident_id' => $case->id, 'affected_document_type' => 'Barangay Clearance',
        'resident_visible_reason' => 'Please visit the Barangay Hall to update your record.',
        'ends_at' => '2026-12-01 17:00:00', 'next_review_at' => '2026-11-20 09:00:00',
    ]))->assertCreated();
    $this->postJson(route('staff.request-restrictions.review', ResidentRequestRestriction::query()->sole()))->assertOk();

    $refused = $this->actingAs($account, 'resident')->postJson(route('portal.request.store'), phaseThreeDocument());
    $refused->assertUnprocessable()->assertJsonPath('errors.document_type.0', ResidentRequestRestriction::MESSAGE
        .' Message from the barangay: Please visit the Barangay Hall to update your record.'
        .' The hold is set to end on December 1, 2026 5:00 PM.'
        .' Barangay staff will review it on November 20, 2026 9:00 AM.');
    $page = $this->get(route('portal.request.create'))->assertOk()
        ->assertSeeInOrder(['Some requests are on hold', 'Barangay Clearance', 'Message from the barangay:', 'Please visit the Barangay Hall to update your record.', 'Hold ends:', 'December 1, 2026 5:00 PM', 'Next review by barangay staff:', 'November 20, 2026 9:00 AM']);

    foreach ([$refused, $page] as $response) {
        $response->assertDontSee('Confidential reviewed allegations')->assertDontSee('Reviewed case decision')
            ->assertDontSee($case->incident_number)->assertDontSee('Secret case allegation');
    }
});

it('says no end date has been set instead of estimating one', function () {
    $account = User::factory()->resident()->create();
    ResidentRequestRestriction::factory()->reviewed()->create(['resident_id' => $account->resident_id, 'resident_visible_reason' => null, 'ends_at' => null, 'next_review_at' => null]);

    $this->actingAs($account, 'resident')->postJson(route('portal.request.store'), phaseThreeDocument())
        ->assertUnprocessable()
        ->assertJsonPath('errors.document_type.0', ResidentRequestRestriction::MESSAGE.' No end date has been set. Barangay staff will review it; ask at the Barangay Hall for help.');
    $this->get(route('portal.request.create'))->assertOk()
        ->assertSeeInOrder(['Some requests are on hold', 'All document requests', 'No end date has been set.'])
        ->assertDontSee('Hold ends:')->assertDontSee('Message from the barangay:');
});

it('lists on the request page only reviewed holds in force for the signed-in resident', function () {
    $account = User::factory()->resident()->create();
    ResidentRequestRestriction::factory()->create(['resident_id' => $account->resident_id, 'resident_visible_reason' => 'Pending decision message']);
    ResidentRequestRestriction::factory()->reviewed()->create(['resident_id' => $account->resident_id, 'resident_visible_reason' => 'Ended hold message', 'ends_at' => now()->subMinute()]);
    ResidentRequestRestriction::factory()->reviewed()->create(['resident_visible_reason' => 'Another resident message']);

    $this->actingAs($account, 'resident')->get(route('portal.request.create'))->assertOk()
        ->assertDontSee('Some requests are on hold')
        ->assertDontSee('Pending decision message')->assertDontSee('Ended hold message')->assertDontSee('Another resident message');
});

it('keeps incident and BPO details out of resident pages exports and other residents requests', function () {
    $account = User::factory()->resident()->create();
    $other = User::factory()->resident()->create();
    $case = Incident::factory()->create(['respondent_resident_id' => $account->resident_id, 'details' => 'Secret case allegation']);
    BarangayProtectionOrder::factory()->create(['incident_id' => $case->id, 'internal_remarks' => 'Secret protection order']);
    ResidentRequestRestriction::factory()->reviewed()->create(['resident_id' => $account->resident_id, 'incident_id' => $case->id, 'internal_reason' => 'Secret restriction notes']);
    $this->actingAs($account, 'resident')->get(route('portal.account'))->assertOk()->assertDontSee('Secret case allegation')->assertDontSee('Secret protection order')->assertDontSee('Secret restriction notes');
    $request = $other->resident->documentRequests()->create(['reference_code' => 'REQ-OTHER-PRIVATE', 'document_type' => 'Barangay Clearance', 'full_name' => $other->resident->full_name, 'address' => $other->resident->address, 'status' => 'pending']);
    $this->getJson(route('portal.request.status', $request->reference_code))->assertNotFound();
    $export = spreadsheetSheetXml($this->actingAs(User::factory()->assignedOperations()->create(['role' => 'staff']), 'web')->get(route('staff.residents.export'))->assertOk());
    expect($export)->not->toContain('Secret case allegation')->not->toContain('Secret protection order')->not->toContain('Secret restriction notes');
});

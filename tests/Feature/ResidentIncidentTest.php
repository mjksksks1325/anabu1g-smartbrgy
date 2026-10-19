<?php

use App\CertificateType;
use App\Models\BarangayProtectionOrder;
use App\Models\Incident;
use App\Models\User;
use App\PortalLocalization;

test('resident pages no longer offer incident reporting', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('Report an incident')
        ->assertDontSee('/portal/incidents');

    $this->get(route('portal.login'))->assertOk()->assertDontSee('incident reporting');
    $this->get(route('portal.register'))->assertOk()->assertDontSee('incident reports');

    $resident = User::factory()->resident()->create();
    $this->actingAs($resident, 'resident')
        ->get(route('portal.account'))
        ->assertOk()
        ->assertDontSee('Report an incident')
        ->assertDontSee('/portal/incidents');
});

test('former resident incident endpoints are unavailable to guests and residents', function () {
    $this->get('/portal/incidents/create')->assertNotFound();
    $this->post('/portal/incidents', ['incident_type' => 'Noise complaint'])->assertNotFound();

    $this->actingAs(User::factory()->resident()->create(), 'resident');
    $this->get('/portal/incidents/create')->assertNotFound();
    $this->post('/portal/incidents', ['incident_type' => 'Noise complaint'])->assertNotFound();
    $this->assertDatabaseCount('incidents', 0);
});

test('barangay officials retain access to existing incident records', function () {
    $staff = User::factory()->assignedOperations()->create(['role' => 'staff']);
    $incident = Incident::factory()->for($staff, 'reporter')->create();

    $this->actingAs($staff)
        ->getJson(route('staff.incidents.index'))
        ->assertOk()
        ->assertJsonPath('data.0.incident_number', $incident->incident_number);
});

test('resident sees a translated neutral notice for a linked pending ordinary incident and can still request a document', function (string $locale, string $notice) {
    $resident = User::factory()->resident()->create();
    Incident::factory()->create([
        'status' => 'under_review',
        'respondent_resident_id' => $resident->resident_id,
        'incident_number' => 'INC-PRIVATE-REFERENCE',
        'details' => 'Private allegation details must stay hidden.',
    ]);

    $this->withCookie(PortalLocalization::COOKIE, $locale)
        ->actingAs($resident, 'resident')
        ->get(route('portal.request.create'))
        ->assertOk()
        ->assertSee($notice, false)
        ->assertDontSee('INC-PRIVATE-REFERENCE')
        ->assertDontSee('Private allegation details');

    $this->postJson(route('portal.request.store'), [
        'document_type' => CertificateType::CertificateOfResidency->value,
        'purpose' => 'Employment',
    ])->assertOk()->assertJsonPath('success', true);
})->with([
    'English' => ['en', 'A pending incident report has been linked to your resident record'],
    'Filipino' => ['fil', 'May incident report na naka-link sa resident record mo'],
]);

test('resident notice excludes sensitive and completed incident reports', function () {
    $resident = User::factory()->resident()->create();
    Incident::factory()->create([
        'status' => 'open',
        'is_sensitive' => true,
        'respondent_resident_id' => $resident->resident_id,
    ]);
    $restrictedIncident = Incident::factory()->create([
        'status' => 'open',
        'respondent_resident_id' => $resident->resident_id,
    ]);
    BarangayProtectionOrder::factory()->create([
        'incident_id' => $restrictedIncident->id,
        'respondent_resident_id' => $resident->resident_id,
    ]);
    Incident::factory()->create([
        'status' => 'resolved',
        'respondent_resident_id' => $resident->resident_id,
    ]);

    $this->actingAs($resident, 'resident')
        ->get(route('portal.request.create'))
        ->assertOk()
        ->assertDontSee('A pending incident report has been linked to your resident record');
});

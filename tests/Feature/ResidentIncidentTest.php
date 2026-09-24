<?php

use App\Models\Incident;
use App\Models\User;

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
    $staff = User::factory()->create(['role' => 'staff']);
    $incident = Incident::factory()->for($staff, 'reporter')->create();

    $this->actingAs($staff)
        ->getJson(route('admin.incidents.index'))
        ->assertOk()
        ->assertJsonPath('data.0.incident_number', $incident->incident_number);
});

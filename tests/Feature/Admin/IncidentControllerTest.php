<?php

use App\Models\Incident;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function incidentPayload(array $overrides = []): array
{
    return [
        'incident_type' => 'Noise Complaint',
        'occurred_date' => today()->toDateString(),
        'occurred_time' => '20:30',
        'location' => 'Purok 1 - Sampaguita',
        'complainant_name' => 'Maria Santos',
        'respondent_name' => 'Juan Reyes',
        'severity' => 'medium',
        'details' => 'Repeated loud music was reported after barangay quiet hours.',
        ...$overrides,
    ];
}

it('requires authentication for every incident endpoint', function () {
    $incident = Incident::factory()->create();

    $this->getJson(route('admin.incidents.index'))->assertUnauthorized();
    $this->postJson(route('admin.incidents.store'), incidentPayload())->assertUnauthorized();
    $this->getJson(route('admin.incidents.show', $incident))->assertUnauthorized();
    $this->patchJson(route('admin.incidents.update', $incident), [])->assertUnauthorized();
    $this->deleteJson(route('admin.incidents.destroy', $incident))->assertUnauthorized();
});

it('forbids viewers from incident records', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);

    $this->actingAs($viewer)->getJson(route('admin.incidents.index'))->assertForbidden();
    $this->actingAs($viewer)->postJson(route('admin.incidents.store'), incidentPayload())->assertForbidden();
});

it('files a validated incident with a private attachment', function () {
    Storage::fake('local');
    $staff = User::factory()->create(['role' => 'staff']);
    $attachment = UploadedFile::fake()->image('evidence.jpg');

    $response = $this->actingAs($staff)->post(route('admin.incidents.store'), [
        ...incidentPayload(),
        'attachments' => [$attachment],
    ], ['Accept' => 'application/json']);

    $response->assertCreated()
        ->assertJsonPath('incident.incident_type', 'Noise Complaint')
        ->assertJsonPath('incident.status', 'pending')
        ->assertJsonPath('incident.reporter_name', $staff->name)
        ->assertJsonPath('incident.attachments.0.name', 'evidence.jpg');

    $incident = Incident::query()->sole();
    expect($incident->incident_number)->toStartWith('INC-'.now()->format('Y').'-');
    Storage::disk('local')->assertExists($incident->attachments[0]['path']);
});

it('rejects invalid incident data and unsafe attachments', function () {
    Storage::fake('local');
    $staff = User::factory()->create();

    $this->actingAs($staff)->post(route('admin.incidents.store'), [
        ...incidentPayload([
            'occurred_date' => today()->addDay()->toDateString(),
            'details' => 'Short',
        ]),
        'attachments' => [UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream')],
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['occurred_date', 'details', 'attachments.0']);

    expect(Incident::query()->count())->toBe(0);
});

it('filters incident reports and returns authoritative summary counts', function () {
    $staff = User::factory()->create();
    Incident::factory()->for($staff, 'reporter')->create([
        'incident_type' => 'Theft',
        'severity' => 'high',
        'status' => 'under_investigation',
    ]);
    Incident::factory()->for($staff, 'reporter')->create([
        'incident_type' => 'Noise Complaint',
        'severity' => 'low',
        'status' => 'resolved',
        'resolved_at' => now(),
    ]);

    $this->actingAs($staff)->getJson(route('admin.incidents.index', [
        'search' => 'Theft',
        'status' => 'under_investigation',
        'severity' => 'high',
    ]))->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.incident_type', 'Theft')
        ->assertJsonPath('summary.pending', 1)
        ->assertJsonPath('summary.resolved_this_month', 1)
        ->assertJsonPath('summary.high', 1);
});

it('requires resolution notes before resolving an incident', function () {
    $staff = User::factory()->create();
    $incident = Incident::factory()->for($staff, 'reporter')->create();

    $this->actingAs($staff)->patchJson(route('admin.incidents.update', $incident), [
        ...incidentPayload(),
        'status' => 'resolved',
        'resolution_notes' => '',
    ])->assertUnprocessable()->assertJsonValidationErrors('resolution_notes');

    $this->actingAs($staff)->patchJson(route('admin.incidents.update', $incident), [
        ...incidentPayload(),
        'status' => 'resolved',
        'resolution_notes' => 'Both parties reached a documented settlement.',
    ])->assertOk()->assertJsonPath('incident.status', 'resolved');

    expect($incident->fresh())
        ->resolved_at->not->toBeNull()
        ->resolution_notes->toBe('Both parties reached a documented settlement.');
});

it('allows only administrators to archive incidents', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $admin = User::factory()->create(['role' => 'admin']);
    $incident = Incident::factory()->for($staff, 'reporter')->create();

    $this->actingAs($staff)->deleteJson(route('admin.incidents.destroy', $incident))->assertForbidden();
    $this->actingAs($admin)->deleteJson(route('admin.incidents.destroy', $incident))->assertOk();

    $this->assertSoftDeleted($incident);
});

it('serves incident attachments only to authorized staff', function () {
    Storage::fake('local');
    Storage::disk('local')->put('incident-attachments/evidence.pdf', 'evidence');
    $staff = User::factory()->create();
    $incident = Incident::factory()->for($staff, 'reporter')->create([
        'attachments' => [[
            'name' => 'evidence.pdf',
            'path' => 'incident-attachments/evidence.pdf',
            'mime' => 'application/pdf',
            'size' => 8,
        ]],
    ]);

    $this->get(route('admin.incidents.attachments.show', [$incident, 0]))->assertRedirect(route('login'));
    $this->actingAs($staff)->get(route('admin.incidents.attachments.show', [$incident, 0]))
        ->assertOk()
        ->assertDownload('evidence.pdf');
});

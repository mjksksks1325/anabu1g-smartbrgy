<?php

use App\Models\DocumentRequest;
use App\Models\Incident;
use App\Models\IssuedCertificate;
use App\Models\Resident;
use App\Models\User;

it('requires staff access for the dashboard summary', function () {
    $this->getJson(route('admin.dashboard.summary'))->assertUnauthorized();

    $viewer = User::factory()->create(['role' => 'viewer']);
    $this->actingAs($viewer)->getJson(route('admin.dashboard.summary'))->assertForbidden();
});

it('returns database-backed dashboard totals and recent activity', function () {
    $staff = User::factory()->create();
    Resident::factory()->count(2)->create(['status' => 'active']);
    Resident::factory()->create(['status' => 'inactive']);
    $resident = Resident::factory()->create(['status' => 'active']);
    $documentRequest = DocumentRequest::query()->create([
        'resident_id' => $resident->id,
        'reference_code' => 'REQ-DASH-001',
        'document_type' => 'Barangay Clearance',
        'full_name' => $resident->full_name,
        'address' => $resident->address,
        'status' => 'processing',
    ]);
    DocumentRequest::query()->create([
        'reference_code' => 'REQ-DASH-002',
        'document_type' => 'Certificate of Residency',
        'full_name' => 'Released Resident',
        'address' => 'Anabu I-G',
        'status' => 'released',
    ]);
    IssuedCertificate::query()->create([
        'document_request_id' => $documentRequest->id,
        'resident_id' => $resident->id,
        'certificate_number' => 'CERT-DASH-001',
        'verification_code' => 'VERIFY-DASH-001',
        'certificate_type' => 'Barangay Clearance',
        'resident_name' => $resident->full_name,
        'issued_at' => now(),
        'issued_by' => $staff->name,
    ]);
    Incident::factory()->for($staff, 'reporter')->create([
        'incident_type' => 'Property Dispute',
        'status' => 'pending',
    ]);
    Incident::factory()->for($staff, 'reporter')->create([
        'status' => 'resolved',
        'resolved_at' => now(),
    ]);

    $this->actingAs($staff)->getJson(route('admin.dashboard.summary'))
        ->assertOk()
        ->assertJsonPath('summary.active_residents', 3)
        ->assertJsonPath('summary.issued_certificates', 1)
        ->assertJsonPath('summary.pending_requests', 1)
        ->assertJsonPath('summary.open_incidents', 1)
        ->assertJsonPath('certificate_requests.Barangay Clearance', 1)
        ->assertJsonPath('certificate_requests.Certificate of Residency', 1)
        ->assertJsonCount(4, 'recent_activity');
});

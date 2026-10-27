<?php

use App\Models\DocumentRequest;
use App\Models\Incident;
use App\Models\IssuedCertificate;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

it('requires staff access for the dashboard summary', function () {
    $this->getJson(route('staff.dashboard.summary'))->assertUnauthorized();

    $viewer = User::factory()->assignedOperations()->create(['role' => 'viewer']);
    $this->actingAs($viewer)->getJson(route('staff.dashboard.summary'))->assertForbidden();
});

it('returns database-backed dashboard totals and recent activity', function () {
    $staff = User::factory()->assignedOperations()->create();
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

    $this->actingAs($staff)->getJson(route('staff.dashboard.summary'))
        ->assertOk()
        ->assertJsonPath('summary.active_residents', 3)
        ->assertJsonPath('summary.issued_certificates', 1)
        ->assertJsonPath('summary.pending_requests', 1)
        ->assertJsonPath('summary.open_incidents', 1)
        ->assertJsonPath('certificate_requests.Barangay Clearance', 1)
        ->assertJsonPath('certificate_requests.Certificate of Residency', 1)
        ->assertJsonCount(4, 'recent_activity')
        ->assertJsonFragment(['reference' => 'REQ-DASH-001', 'record_id' => $documentRequest->id]);
});

it('shows document releases by other staff in the dashboard activity feed', function () {
    Storage::fake('public');
    $secretary = User::factory()->assignedOperations()->create(['name' => 'Barangay Secretary']);
    $viewer = User::factory()->assignedOperations()->create(['name' => 'Kagawad']);
    $documentRequest = DocumentRequest::query()->create([
        'reference_code' => 'REQ-DASH-LIVE-001',
        'document_type' => 'Barangay Clearance',
        'full_name' => 'Juan Dela Cruz',
        'address' => 'Anabu I-G, Imus City',
        'purpose' => 'Employment',
        'status' => 'ready_for_release',
    ]);

    $this->actingAs($secretary)
        ->postJson(route('staff.document-requests.issue', $documentRequest))
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->actingAs($viewer)
        ->getJson(route('staff.dashboard.summary'))
        ->assertOk()
        ->assertJsonPath('recent_activity.0.title', 'Document request released by Barangay Secretary')
        ->assertJsonPath('recent_activity.0.detail', 'REQ-DASH-LIVE-001 — Barangay Clearance');

    $this->assertDatabaseHas('administrative_audits', [
        'user_id' => $secretary->id,
        'action' => 'admin.document-requests.issue',
        'record' => (string) $documentRequest->id,
    ]);
});

it('renders the staff language toggle as inline links with an icon theme control', function () {
    $this->actingAs(User::factory()->assignedOperations()->create(['role' => 'staff']))
        ->get(route('staff.dashboard'))
        ->assertOk()
        ->assertSee('class="admin-locale-toggle admin-language-inline"', false)
        ->assertSee('>English</button>', false)
        ->assertSee('>Filipino</button>', false)
        ->assertSee('class="dark-mode-toggle staff-theme-icon"', false)
        ->assertSee('staff-theme-moon', false);
});

it('keeps certificate requests and recent activity in the original two-column dashboard layout', function () {
    $this->actingAs(User::factory()->assignedOperations()->create(['role' => 'staff']))
        ->get(route('staff.dashboard'))
        ->assertOk()
        ->assertSee('class="two-col dashboard-overview-grid"', false)
        ->assertSee('id="dash-chart"', false)
        ->assertSee('id="dash-recent-activity"', false);
});

<?php

use App\Models\DocumentRequest;
use App\Models\IssuedCertificate;
use App\Models\User;

test('guests receive 401 from the live document request feed', function () {
    $this->getJson(route('staff.document-requests.live'))
        ->assertUnauthorized();
});

test('authenticated users receive all available request detail fields', function () {
    $user = User::factory()->assignedOperations()->create();

    $documentRequest = DocumentRequest::query()->create([
        'reference_code' => 'REQ-2026-DETAIL',
        'document_type' => 'Certificate of Residency',
        'full_name' => 'Maverick Jan Marquez',
        'date_of_birth' => '2000-01-15',
        'address' => '38 Purok 1 Sta. Isabel Dinalupihan, Bataan',
        'contact_number' => '09171234567',
        'email' => 'maverick@example.com',
        'purpose' => 'Para sa trabaho',
        'status' => 'released',
    ]);

    $this->actingAs($user)
        ->getJson(route('staff.document-requests.live'))
        ->assertOk()
        ->assertJsonFragment([
            'id' => $documentRequest->id,
            'reference_code' => 'REQ-2026-DETAIL',
            'date_of_birth' => '2000-01-15',
            'address' => '38 Purok 1 Sta. Isabel Dinalupihan, Bataan',
            'contact_number' => '09171234567',
            'email' => 'maverick@example.com',
            'purpose' => 'Para sa trabaho',
        ])
        ->assertJsonStructure([
            [
                'created_at',
            ],
        ]);
});

/** @param array<string, mixed> $attributes */
function boardRequest(array $attributes): DocumentRequest
{
    return DocumentRequest::query()->create([
        'reference_code' => 'REQ-'.fake()->unique()->numerify('######'),
        'document_type' => 'Barangay Clearance',
        'full_name' => 'Juan Dela Cruz',
        'address' => 'Anabu I-G, Imus City',
        ...$attributes,
    ]);
}

test('the certificates board loads one lane at a time with lane counts that skip rejected requests', function () {
    boardRequest(['source' => 'online', 'status' => 'pending', 'full_name' => 'Online Pending']);
    boardRequest(['source' => 'onsite', 'status' => 'pending', 'full_name' => 'Walk-in Pending']);
    boardRequest(['source' => 'online', 'status' => 'processing']);
    boardRequest(['status' => 'approved', 'full_name' => 'Approved Not Yet Ready']);
    boardRequest(['status' => 'ready_for_release', 'full_name' => 'Ready For Release']);
    boardRequest(['status' => 'released']);
    boardRequest(['source' => 'online', 'status' => 'rejected']);

    $staff = User::factory()->create(['role' => 'staff', 'staff_permissions' => ['documents.view']]);

    $this->actingAs($staff)
        ->getJson(route('staff.document-requests.list', ['lane' => 'online', 'with_counts' => 1]))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.full_name', 'Online Pending')
        ->assertJsonPath('counts', ['online' => 1, 'processing' => 3, 'ready' => 1, 'completed' => 1]);

    // An approved request cannot be issued until it is marked ready, so it waits in Processing, not Ready to Print.
    $this->getJson(route('staff.document-requests.list', ['lane' => 'processing']))
        ->assertOk()
        ->assertJsonPath('total', 3)
        ->assertJsonFragment(['full_name' => 'Walk-in Pending'])
        ->assertJsonFragment(['full_name' => 'Approved Not Yet Ready'])
        ->assertJsonMissingPath('counts');
    $this->getJson(route('staff.document-requests.list', ['lane' => 'ready']))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.full_name', 'Ready For Release');
});

test('board search reaches older requests beyond the first page of a lane', function () {
    $oldest = boardRequest(['status' => 'released', 'full_name' => 'Lourdes Older Record', 'reference_code' => 'REQ-2019-OLDEST']);
    $oldest->forceFill(['created_at' => now()->subYears(5)])->save();
    foreach (range(1, 3) as $index) {
        boardRequest(['status' => 'released']);
    }

    $staff = User::factory()->create(['role' => 'staff', 'staff_permissions' => ['documents.view']]);

    $this->actingAs($staff)
        ->getJson(route('staff.document-requests.list', ['lane' => 'completed', 'per_page' => 2]))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('total', 4)
        ->assertJsonMissing(['reference_code' => 'REQ-2019-OLDEST']);

    $this->getJson(route('staff.document-requests.list', ['lane' => 'completed', 'per_page' => 2, 'search' => '2019-OLDEST', 'with_counts' => 1]))
        ->assertOk()
        ->assertJsonPath('data.0.full_name', 'Lourdes Older Record')
        ->assertJsonPath('matching.completed', 1)
        ->assertJsonPath('counts.completed', 4);
});

test('the board list keeps its permission and rejects unbounded pages', function () {
    $this->getJson(route('staff.document-requests.list', ['lane' => 'online']))->assertUnauthorized();

    $this->actingAs(User::factory()->create(['role' => 'staff', 'staff_permissions' => ['records.view']]))
        ->getJson(route('staff.document-requests.list', ['lane' => 'online']))
        ->assertForbidden();

    $this->actingAs(User::factory()->create(['role' => 'staff', 'staff_permissions' => ['documents.view']]))
        ->getJson(route('staff.document-requests.list', ['per_page' => 500, 'lane' => 'archived']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page', 'lane']);
});

test('the certificates page no longer embeds every request in its HTML', function () {
    boardRequest(['status' => 'released', 'full_name' => 'Embedded Name Check']);

    $this->actingAs(User::factory()->create(['role' => 'staff', 'staff_permissions' => ['documents.view']]))
        ->get(route('staff.document-requests.index'))
        ->assertOk()
        ->assertDontSee('Embedded Name Check')
        ->assertDontSee('LARAVEL_DOCUMENT_REQUESTS');
});

test('requests cannot be marked released without issuing a certificate', function () {
    $user = User::factory()->assignedOperations()->create();
    $documentRequest = DocumentRequest::query()->create([
        'reference_code' => 'REQ-2026-NOBYPASS',
        'document_type' => 'Barangay Clearance',
        'full_name' => 'Juan Dela Cruz',
        'address' => 'Anabu I-G, Imus City',
        'status' => 'ready_for_release',
    ]);

    $this->actingAs($user)
        ->patchJson(route('staff.document-requests.update-status', $documentRequest), [
            'status' => 'released',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    expect($documentRequest->fresh()->status)->toBe('ready_for_release');
});

test('request status is locked after its certificate has been issued', function () {
    $user = User::factory()->assignedOperations()->create();
    $documentRequest = DocumentRequest::query()->create([
        'reference_code' => 'REQ-2026-LOCKED',
        'document_type' => 'Barangay Clearance',
        'full_name' => 'Juan Dela Cruz',
        'address' => 'Anabu I-G, Imus City',
        'status' => 'released',
    ]);
    IssuedCertificate::query()->create([
        'document_request_id' => $documentRequest->id,
        'certificate_number' => 'CERT-2026-LOCKED',
        'verification_code' => 'LOCKEDVERIFYCODE',
        'certificate_type' => 'Barangay Clearance',
        'resident_name' => 'Juan Dela Cruz',
        'amount_paid' => 50,
        'issued_at' => now(),
    ]);

    $this->actingAs($user)
        ->patchJson(route('staff.document-requests.update-status', $documentRequest), [
            'status' => 'processing',
        ])
        ->assertConflict()
        ->assertJsonPath('message', 'This request already has an issued certificate and its status is locked.');

    expect($documentRequest->fresh()->status)->toBe('released');
});

test('rejecting an online request requires a meaningful reason', function () {
    $user = User::factory()->assignedOperations()->create();
    $documentRequest = DocumentRequest::query()->create([
        'reference_code' => 'REQ-2026-REJECT-REASON',
        'document_type' => 'Barangay Clearance',
        'full_name' => 'Juan Dela Cruz',
        'address' => 'Anabu I-G, Imus City',
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->patchJson(route('staff.document-requests.update-status', $documentRequest), [
            'status' => 'rejected',
            'rejection_reason' => 'Too short',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('rejection_reason');

    expect($documentRequest->fresh()->status)->toBe('pending');
});

test('rejecting an online request records the reason actor and timestamp', function () {
    $user = User::factory()->assignedOperations()->create();
    $documentRequest = DocumentRequest::query()->create([
        'reference_code' => 'REQ-2026-REJECTED',
        'source' => 'online',
        'document_type' => 'Barangay Clearance',
        'full_name' => 'Maria Santos',
        'address' => 'Anabu I-G, Imus City',
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->patchJson(route('staff.document-requests.update-status', $documentRequest), [
            'status' => 'rejected',
            'rejection_reason' => 'The submitted address does not match the resident record.',
        ])
        ->assertOk()
        ->assertJsonPath('request.status', 'rejected')
        ->assertJsonPath('request.rejection_reason', 'The submitted address does not match the resident record.');

    $documentRequest->refresh();

    expect($documentRequest->rejected_by)->toBe($user->id);
    expect($documentRequest->rejected_at)->not->toBeNull();
});

test('users without a staff role cannot reject online requests', function () {
    $user = User::factory()->assignedOperations()->create();
    $user->forceFill(['role' => 'viewer'])->save();
    $documentRequest = DocumentRequest::query()->create([
        'reference_code' => 'REQ-2026-FORBIDDEN',
        'document_type' => 'Barangay Clearance',
        'full_name' => 'Pedro Reyes',
        'address' => 'Anabu I-G, Imus City',
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->patchJson(route('staff.document-requests.update-status', $documentRequest), [
            'status' => 'rejected',
            'rejection_reason' => 'The submitted requirements could not be verified.',
        ])
        ->assertForbidden();

    expect($documentRequest->fresh()->status)->toBe('pending');
});

test('board cards carry only the number and revocation of their issued certificate, so details can offer the reprint', function () {
    $released = boardRequest(['status' => 'released', 'full_name' => 'Released With Certificate']);
    $certificate = IssuedCertificate::query()->create([
        'document_request_id' => $released->id, 'certificate_number' => 'CERT-2026-BOARD1', 'verification_code' => 'BOARDVERIFYCODE1',
        'certificate_type' => 'Barangay Clearance', 'resident_name' => 'Released With Certificate', 'issued_at' => now(),
        'photo_path' => 'certificate-photos/private.jpg', 'resident_snapshot' => ['full_name' => 'Released With Certificate'],
    ]);
    boardRequest(['status' => 'released', 'full_name' => 'Released Without Certificate']);

    $response = $this->actingAs(User::factory()->create(['role' => 'staff', 'staff_permissions' => ['documents.view']]))
        ->getJson(route('staff.document-requests.list', ['lane' => 'completed']))
        ->assertOk();

    $cards = collect($response->json('data'))->keyBy('full_name');
    expect($cards['Released With Certificate']['issued_certificate'])->toBe(['id' => $certificate->id, 'document_request_id' => $released->id, 'certificate_number' => 'CERT-2026-BOARD1', 'revoked_at' => null])
        ->and($cards['Released Without Certificate']['issued_certificate'])->toBeNull();
    expect($response->getContent())->not->toContain('BOARDVERIFYCODE1')->not->toContain('private.jpg');
});

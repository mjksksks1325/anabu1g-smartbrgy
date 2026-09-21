<?php

use App\Models\DocumentRequest;
use App\Models\IssuedCertificate;
use App\Models\User;

test('guests receive 401 from the live document request feed', function () {
    $this->getJson(route('admin.document-requests.live'))
        ->assertUnauthorized();
});

test('authenticated users receive all available request detail fields', function () {
    $user = User::factory()->create();

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
        ->getJson(route('admin.document-requests.live'))
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

test('requests cannot be marked released without issuing a certificate', function () {
    $user = User::factory()->create();
    $documentRequest = DocumentRequest::query()->create([
        'reference_code' => 'REQ-2026-NOBYPASS',
        'document_type' => 'Barangay Clearance',
        'full_name' => 'Juan Dela Cruz',
        'address' => 'Anabu I-G, Imus City',
        'status' => 'ready_for_release',
    ]);

    $this->actingAs($user)
        ->patchJson(route('admin.document-requests.update-status', $documentRequest), [
            'status' => 'released',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    expect($documentRequest->fresh()->status)->toBe('ready_for_release');
});

test('request status is locked after its certificate has been issued', function () {
    $user = User::factory()->create();
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
        ->patchJson(route('admin.document-requests.update-status', $documentRequest), [
            'status' => 'processing',
        ])
        ->assertConflict()
        ->assertJsonPath('message', 'This request already has an issued certificate and its status is locked.');

    expect($documentRequest->fresh()->status)->toBe('released');
});

test('rejecting an online request requires a meaningful reason', function () {
    $user = User::factory()->create();
    $documentRequest = DocumentRequest::query()->create([
        'reference_code' => 'REQ-2026-REJECT-REASON',
        'document_type' => 'Barangay Clearance',
        'full_name' => 'Juan Dela Cruz',
        'address' => 'Anabu I-G, Imus City',
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->patchJson(route('admin.document-requests.update-status', $documentRequest), [
            'status' => 'rejected',
            'rejection_reason' => 'Too short',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('rejection_reason');

    expect($documentRequest->fresh()->status)->toBe('pending');
});

test('rejecting an online request records the reason actor and timestamp', function () {
    $user = User::factory()->create();
    $documentRequest = DocumentRequest::query()->create([
        'reference_code' => 'REQ-2026-REJECTED',
        'source' => 'online',
        'document_type' => 'Barangay Clearance',
        'full_name' => 'Maria Santos',
        'address' => 'Anabu I-G, Imus City',
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->patchJson(route('admin.document-requests.update-status', $documentRequest), [
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
    $user = User::factory()->create();
    $user->forceFill(['role' => 'viewer'])->save();
    $documentRequest = DocumentRequest::query()->create([
        'reference_code' => 'REQ-2026-FORBIDDEN',
        'document_type' => 'Barangay Clearance',
        'full_name' => 'Pedro Reyes',
        'address' => 'Anabu I-G, Imus City',
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->patchJson(route('admin.document-requests.update-status', $documentRequest), [
            'status' => 'rejected',
            'rejection_reason' => 'The submitted requirements could not be verified.',
        ])
        ->assertForbidden();

    expect($documentRequest->fresh()->status)->toBe('pending');
});

<?php

use App\Models\DocumentRequest;
use App\Models\Resident;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('portal renders email as the required notification field', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('id="f-email"', false)
        ->assertSee('type="email"', false)
        ->assertSee('required', false)
        ->assertDontSee('id="f-contact"', false)
        ->assertSee('Email Address');
});

test('portal assets use https when the request is forwarded through a trusted proxy', function () {
    $this->withHeader('X-Forwarded-Proto', 'https')
        ->get('http://public.example.test/portal')
        ->assertOk()
        ->assertSee('href="https://public.example.test/css/portal.css"', false)
        ->assertSee('src="https://public.example.test/images/anabu-logo.jpg"', false);
});

test('portal can refresh its csrf token after the public session expires', function () {
    $response = $this->getJson(route('portal.csrf-token'));

    $response
        ->assertOk()
        ->assertJsonStructure(['token']);

    expect($response->json('token'))->toBeString()->not->toBeEmpty();
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('portal submission retries once with a refreshed csrf token after a 419 response', function () {
    $script = file_get_contents(public_path('js/document-request.js'));

    expect($script)
        ->toContain('let response = await send(await refreshPortalCsrfToken())')
        ->toContain('if (response.status === 419)')
        ->toContain("fetch('/portal/csrf-token'")
        ->toContain("credentials: 'same-origin'")
        ->toContain('sendPortalDocumentRequest(formData)');
});

test('portal requests store the required notification email', function () {
    $response = $this->postJson(route('portal.request.store'), [
        'document_type' => 'Certificate of Residency',
        'full_name' => 'Maverick Jan Marquez',
        'date_of_birth' => '2000-01-15',
        'address' => '38 Purok 1 Sta. Isabel Dinalupihan, Bataan',
        'contact_number' => '09171234567',
        'email' => 'maverick@example.com',
        'purpose' => 'Employment',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('document_requests', [
        'full_name' => 'Maverick Jan Marquez',
        'contact_number' => null,
        'email' => 'maverick@example.com',
    ]);
});

test('portal requests link exactly one resident with the same normalized name and birth date', function () {
    $resident = Resident::factory()->create([
        'first_name' => 'Maria',
        'middle_name' => 'Reyes',
        'last_name' => 'Santos',
        'date_of_birth' => '1990-05-14',
    ]);

    $this->postJson(route('portal.request.store'), [
        'document_type' => 'Certificate of Residency',
        'full_name' => '  MARIA   REYES SANTOS ',
        'date_of_birth' => '1990-05-14',
        'address' => 'Anabu I-G, Imus City',
        'email' => 'maria@example.com',
    ])->assertOk();

    expect(DocumentRequest::query()->sole()->resident_id)->toBe($resident->id);
});

test('portal requests stay unlinked when resident identity is ambiguous', function () {
    Resident::factory()->count(2)->create([
        'first_name' => 'Maria',
        'middle_name' => 'Reyes',
        'last_name' => 'Santos',
        'date_of_birth' => '1990-05-14',
    ]);

    $this->postJson(route('portal.request.store'), [
        'document_type' => 'Certificate of Residency',
        'full_name' => 'Maria Reyes Santos',
        'date_of_birth' => '1990-05-14',
        'address' => 'Anabu I-G, Imus City',
        'email' => 'maria@example.com',
    ])->assertOk();

    expect(DocumentRequest::query()->sole()->resident_id)->toBeNull();
});

test('portal requests require an email address for notifications', function () {
    $response = $this->postJson(route('portal.request.store'), [
        'document_type' => 'Certificate of Residency',
        'full_name' => 'Maverick Jan Marquez',
        'date_of_birth' => '2000-01-15',
        'address' => '38 Purok 1 Sta. Isabel Dinalupihan, Bataan',
        'purpose' => 'Employment',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->assertDatabaseCount('document_requests', 0);
});

test('portal requests reject an invalid email address', function () {
    $response = $this->postJson(route('portal.request.store'), [
        'document_type' => 'Certificate of Residency',
        'full_name' => 'Maverick Jan Marquez',
        'date_of_birth' => '2000-01-15',
        'address' => '38 Purok 1 Sta. Isabel Dinalupihan, Bataan',
        'email' => 'not-an-email',
        'purpose' => 'Employment',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->assertDatabaseCount('document_requests', 0);
});

test('portal requests store an uploaded valid ID', function () {
    Storage::fake('public');
    $attachment = UploadedFile::fake()->image('valid-id.jpg');

    $response = $this->post(route('portal.request.store'), [
        'document_type' => 'Certificate of Residency',
        'full_name' => 'Maverick Jan Marquez',
        'date_of_birth' => '2000-01-15',
        'address' => '38 Purok 1 Sta. Isabel Dinalupihan, Bataan',
        'email' => 'maverick@example.com',
        'purpose' => 'Employment',
        'attachment' => $attachment,
    ], [
        'Accept' => 'application/json',
    ]);

    $response->assertOk();

    $documentRequest = DocumentRequest::query()->firstOrFail();
    $storedPath = Str::after($documentRequest->attachment_path, '/storage/');

    expect($documentRequest->attachment_path)
        ->toStartWith('/storage/document-request-attachments/');
    Storage::disk('public')->assertExists($storedPath);
});

test('portal requests reject unsupported attachment types', function () {
    Storage::fake('public');
    $attachment = UploadedFile::fake()->create(
        'unsafe-script.php',
        10,
        'application/x-php'
    );

    $response = $this->post(route('portal.request.store'), [
        'document_type' => 'Certificate of Residency',
        'full_name' => 'Maverick Jan Marquez',
        'date_of_birth' => '2000-01-15',
        'address' => '38 Purok 1 Sta. Isabel Dinalupihan, Bataan',
        'email' => 'maverick@example.com',
        'attachment' => $attachment,
    ], [
        'Accept' => 'application/json',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('attachment');

    $this->assertDatabaseCount('document_requests', 0);
});

test('portal requests reject a document type that the barangay does not issue', function () {
    $this->postJson(route('portal.request.store'), [
        'document_type' => 'Fake Clearance',
        'full_name' => 'Juan Dela Cruz',
        'address' => 'Anabu I-G, Imus City',
        'email' => 'juan@example.com',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('document_type');

    $this->assertDatabaseCount('document_requests', 0);
});

test('portal status returns the recorded rejection reason to the requester', function () {
    $documentRequest = DocumentRequest::query()->create([
        'reference_code' => 'REQ-2026-PORTAL-REJECTED',
        'source' => 'online',
        'document_type' => 'Barangay Clearance',
        'full_name' => 'Juan Dela Cruz',
        'address' => 'Anabu I-G, Imus City',
        'status' => 'rejected',
        'rejection_reason' => 'The submitted address could not be verified.',
        'rejected_at' => now(),
    ]);

    $this->getJson(route('portal.request.status', $documentRequest->reference_code))
        ->assertOk()
        ->assertJsonPath('status', 'rejected')
        ->assertJsonPath('rejection_reason', 'The submitted address could not be verified.');
});

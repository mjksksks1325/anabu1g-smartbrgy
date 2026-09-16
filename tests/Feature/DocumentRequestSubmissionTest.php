<?php

use App\Models\DocumentRequest;
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

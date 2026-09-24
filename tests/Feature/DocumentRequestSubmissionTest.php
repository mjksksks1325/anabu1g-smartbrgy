<?php

use App\Models\DocumentRequest;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('portal renders email as the required notification field', function () {
    $this->actingAs(User::factory()->resident()->create(), 'resident')->get(route('portal.request.create'))
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
        ->assertSee('href="https://public.example.test/css/portal.css?v=', false)
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

test('portal requests use the authenticated account notification email', function () {
    $user = User::factory()->resident()->create(['email' => 'maverick@example.com']);
    $this->actingAs($user, 'resident');
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
        'full_name' => $user->resident->full_name,
        'resident_id' => $user->resident_id,
        'contact_number' => null,
        'email' => 'maverick@example.com',
    ]);
});

test('portal requests ignore forged identity and link the authenticated resident', function () {
    $resident = Resident::factory()->create([
        'first_name' => 'Maria',
        'middle_name' => 'Reyes',
        'last_name' => 'Santos',
        'date_of_birth' => '1990-05-14',
    ]);

    $user = User::factory()->resident()->create(['resident_id' => $resident->id]);
    $other = Resident::factory()->create();
    $this->actingAs($user, 'resident')->postJson(route('portal.request.store'), [
        'document_type' => 'Certificate of Residency',
        'purpose' => 'Employment',
        'resident_id' => $other->id,
        'full_name' => $other->full_name,
        'date_of_birth' => '1990-05-14',
        'address' => 'Anabu I-G, Imus City',
        'email' => 'maria@example.com',
    ])->assertOk();

    $request = DocumentRequest::query()->sole();
    expect($request->resident_id)->toBe($resident->id);
    expect($request->full_name)->toBe($resident->full_name);
    expect($request->address)->toBe($resident->address);
    expect($request->email)->toBe($user->email);
    expect($request->date_of_birth)->toBe('1990-05-14');
});

test('residents with identical names and birthdays still submit under their linked account', function () {
    $residents = Resident::factory()->count(2)->create([
        'first_name' => 'Maria',
        'middle_name' => 'Reyes',
        'last_name' => 'Santos',
        'date_of_birth' => '1990-05-14',
    ]);

    $user = User::factory()->resident()->create(['resident_id' => $residents->first()->id]);
    $this->actingAs($user, 'resident')->postJson(route('portal.request.store'), [
        'document_type' => 'Certificate of Residency',
        'purpose' => 'Employment',
        'full_name' => 'Maria Reyes Santos',
        'date_of_birth' => '1990-05-14',
        'address' => 'Anabu I-G, Imus City',
        'email' => 'maria@example.com',
    ])->assertOk();

    expect(DocumentRequest::query()->sole()->resident_id)->toBe($user->resident_id);
});

test('portal requests use account email when no email is submitted', function () {
    $user = User::factory()->resident()->create();
    $this->actingAs($user, 'resident');
    $response = $this->postJson(route('portal.request.store'), [
        'document_type' => 'Certificate of Residency',
        'full_name' => 'Maverick Jan Marquez',
        'date_of_birth' => '2000-01-15',
        'address' => '38 Purok 1 Sta. Isabel Dinalupihan, Bataan',
        'purpose' => 'Employment',
    ]);

    $response->assertOk();
    expect(DocumentRequest::query()->sole()->email)->toBe($user->email);
});

test('portal requests ignore invalid client email and use account email', function () {
    $user = User::factory()->resident()->create();
    $this->actingAs($user, 'resident');
    $response = $this->postJson(route('portal.request.store'), [
        'document_type' => 'Certificate of Residency',
        'full_name' => 'Maverick Jan Marquez',
        'date_of_birth' => '2000-01-15',
        'address' => '38 Purok 1 Sta. Isabel Dinalupihan, Bataan',
        'email' => 'not-an-email',
        'purpose' => 'Employment',
    ]);

    $response->assertOk();
    expect(DocumentRequest::query()->sole()->email)->toBe($user->email);
});

test('portal requests store an uploaded valid ID', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->resident()->create(), 'resident');
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
    Storage::disk('local')->assertExists($documentRequest->private_attachment_path);
    expect($documentRequest->getRawOriginal('attachment_path'))->toBeNull();
    expect($documentRequest->toArray())->not->toHaveKey('private_attachment_path');
    $url = route('admin.document-requests.attachment', $documentRequest);
    expect($documentRequest->attachment_path)->toBe($url);
    $this->get($url)->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'viewer']))->get($url)->assertForbidden();
    $response = $this->actingAs(User::factory()->create(['role' => 'staff']))->get($url)->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('portal requests reject unsupported attachment types', function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->resident()->create(), 'resident');
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
    $this->actingAs(User::factory()->resident()->create(), 'resident');
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
    $user = User::factory()->resident()->create();
    $this->actingAs($user, 'resident');
    $documentRequest = DocumentRequest::query()->create([
        'resident_id' => $user->resident_id,
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

<?php

use App\CertificateType;
use App\Models\DocumentRequest;
use App\Models\IssuedCertificate;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use function Pest\Laravel\mock;

dataset('certificate types', [
    'barangay clearance' => [CertificateType::BarangayClearance, 50, 'admin.certificates.barangay-clearance'],
    'residency' => [CertificateType::CertificateOfResidency, 50, 'admin.certificates.residency'],
    'indigency' => [CertificateType::CertificateOfIndigency, 0, 'admin.certificates.indigency'],
    'barangay ID' => [CertificateType::BarangayId, 100, 'admin.certificates.barangay-id'],
    'first-time jobseeker' => [CertificateType::FirstTimeJobseeker, 0, 'admin.certificates.first-time-jobseeker'],
    'business clearance' => [CertificateType::BusinessClearance, 200, 'admin.certificates.business-clearance'],
]);

it('requires authentication for certificate administration endpoints', function () {
    $documentRequest = DocumentRequest::query()->create([
        'reference_code' => 'REQ-2026-GUEST',
        'document_type' => CertificateType::BarangayClearance->value,
        'full_name' => 'Juan Dela Cruz',
        'address' => 'Anabu I-G, Imus City',
        'status' => 'ready_for_release',
    ]);
    $certificate = IssuedCertificate::query()->create([
        'certificate_number' => 'CERT-2026-GUEST',
        'verification_code' => 'GUESTVERIFYCODE1',
        'certificate_type' => CertificateType::BarangayClearance->value,
        'resident_name' => 'Juan Dela Cruz',
        'amount_paid' => 50,
        'issued_at' => now(),
    ]);

    $this->postJson(route('admin.issued-certificates.store'))->assertUnauthorized();
    $this->getJson(route('admin.issued-certificates.index'))->assertUnauthorized();
    $this->postJson(route('admin.document-requests.issue', $documentRequest))->assertUnauthorized();
    $this->get(route('admin.issued-certificates.print', $certificate))
        ->assertRedirect(route('login'));
});

it('lists issued certificates with reprint and verification links', function () {
    $user = User::factory()->create();
    $certificate = IssuedCertificate::query()->create([
        'certificate_number' => 'CERT-2026-HISTORY',
        'verification_code' => 'HISTORYVERIFYCODE',
        'certificate_type' => CertificateType::BarangayClearance->value,
        'resident_name' => 'Maria Santos',
        'amount_paid' => 50,
        'issued_at' => '2026-09-16 09:30:00',
        'issued_by' => 'Records Officer',
    ]);

    $this->actingAs($user)
        ->getJson(route('admin.issued-certificates.index'))
        ->assertOk()
        ->assertJsonPath('0.certificate_number', 'CERT-2026-HISTORY')
        ->assertJsonPath('0.resident_name', 'Maria Santos')
        ->assertJsonPath('0.source', 'onsite')
        ->assertJsonPath('0.print_url', route('admin.issued-certificates.print', $certificate))
        ->assertJsonPath('0.verification_url', route('certificate.verify', 'HISTORYVERIFYCODE'));
});

it('issues every supported online request with the server fee and a real QR file', function (
    CertificateType $certificateType,
    int $expectedFee,
) {
    Storage::fake('public');
    $user = User::factory()->create(['name' => 'Records Officer']);
    $documentRequest = DocumentRequest::query()->create([
        'reference_code' => 'REQ-'.Str::upper(Str::random(12)),
        'document_type' => $certificateType->value,
        'full_name' => 'Maria Santos',
        'address' => 'Anabu I-G, Imus City',
        'purpose' => 'Employment requirement',
        'status' => 'ready_for_release',
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('admin.document-requests.issue', $documentRequest));

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('certificate.certificate_type', $certificateType->value)
        ->assertJsonPath('certificate.issued_by', 'Records Officer');
    $certificate = IssuedCertificate::query()->sole();
    expect((float) $certificate->amount_paid)->toBe((float) $expectedFee);
    expect($certificate->qr_code_path)->toStartWith('/storage/qrcodes/');
    expect($response->json('verification_url'))->toBe(route('certificate.verify', $certificate->verification_code));
    expect($response->json('print_url'))->toBe(route('admin.issued-certificates.print', $certificate));
    Storage::disk('public')->assertExists(Str::after($certificate->qr_code_path, '/storage/'));
    expect($documentRequest->fresh())
        ->status->toBe('released')
        ->remarks->toBe('Certificate issued successfully.');
})->with('certificate types');

it('creates and links an onsite request while using the server fee', function () {
    Storage::fake('public');
    $user = User::factory()->create(['name' => 'Barangay Clerk']);

    $response = $this->actingAs($user)->postJson(route('admin.issued-certificates.store'), [
        'certificate_type' => CertificateType::BusinessClearance->value,
        'resident_name' => 'Ana Reyes',
        'address' => 'Anabu I-G, Imus City',
        'purpose' => 'Business permit',
        'amount_paid' => 1,
    ]);

    $response->assertOk()->assertJsonPath('success', true);

    $certificate = IssuedCertificate::query()->sole();
    $documentRequest = DocumentRequest::query()->sole();

    expect((float) $certificate->amount_paid)->toBe(200.0)
        ->and($certificate->document_request_id)->toBe($documentRequest->id)
        ->and($documentRequest->source)->toBe('onsite')
        ->and($documentRequest->status)->toBe('released')
        ->and($documentRequest->address)->toBe('Anabu I-G, Imus City');

    Storage::disk('public')->assertExists(
        Str::after($certificate->qr_code_path, '/storage/')
    );
});

it('rejects an unsupported manual certificate type', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('admin.issued-certificates.store'), [
        'certificate_type' => 'Fake Clearance',
        'resident_name' => 'Ana Reyes',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('certificate_type');

    expect(IssuedCertificate::query()->count())->toBe(0);
});

it('does not issue an online request before it is ready for release', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $documentRequest = DocumentRequest::query()->create([
        'reference_code' => 'REQ-2026-NOTREADY',
        'document_type' => CertificateType::BarangayClearance->value,
        'full_name' => 'Juan Dela Cruz',
        'address' => 'Anabu I-G, Imus City',
        'status' => 'processing',
    ]);

    $this->actingAs($user)
        ->postJson(route('admin.document-requests.issue', $documentRequest))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This request is not ready for release.');

    expect(IssuedCertificate::query()->count())->toBe(0);
    expect($documentRequest->fresh()->status)->toBe('processing');
    Storage::disk('public')->assertDirectoryEmpty('qrcodes');
});

it('returns 409 and keeps one certificate when the same request is issued twice', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $documentRequest = DocumentRequest::query()->create([
        'reference_code' => 'REQ-2026-DUPLICATE',
        'document_type' => CertificateType::CertificateOfResidency->value,
        'full_name' => 'Juan Dela Cruz',
        'address' => 'Anabu I-G, Imus City',
        'status' => 'ready_for_release',
    ]);

    $this->actingAs($user)
        ->postJson(route('admin.document-requests.issue', $documentRequest))
        ->assertOk();
    $firstCertificate = IssuedCertificate::query()->sole();

    $this->actingAs($user)
        ->postJson(route('admin.document-requests.issue', $documentRequest))
        ->assertConflict()
        ->assertJsonPath('message', 'A certificate has already been issued for this request.');

    expect(IssuedCertificate::query()->count())->toBe(1);
    expect(IssuedCertificate::query()->sole()->is($firstCertificate))->toBeTrue();
});

it('rolls back issuance and request release when the QR file cannot be saved', function () {
    $disk = mock(Filesystem::class);
    $disk->shouldReceive('put')->once()->andReturnFalse();
    $disk->shouldReceive('delete')->once()->andReturnTrue();
    $filesystem = mock(FilesystemManager::class);
    $filesystem->shouldReceive('disk')->twice()->with('public')->andReturn($disk);
    $this->app->instance(FilesystemManager::class, $filesystem);
    $user = User::factory()->create();
    $documentRequest = DocumentRequest::query()->create([
        'reference_code' => 'REQ-2026-QRFAIL',
        'document_type' => CertificateType::BarangayClearance->value,
        'full_name' => 'Juan Dela Cruz',
        'address' => 'Anabu I-G, Imus City',
        'status' => 'ready_for_release',
    ]);

    $this->actingAs($user)
        ->postJson(route('admin.document-requests.issue', $documentRequest))
        ->assertInternalServerError()
        ->assertJsonPath('message', 'The certificate QR code could not be generated. No certificate was issued.');

    expect(IssuedCertificate::query()->count())->toBe(0);
    expect($documentRequest->fresh()->status)->toBe('ready_for_release');
});

it('renders the matching print template for every certificate type', function (
    CertificateType $certificateType,
    int $fee,
    string $expectedView,
) {
    $user = User::factory()->create();
    $certificate = IssuedCertificate::query()->create([
        'certificate_number' => 'CERT-'.Str::upper(Str::random(12)),
        'verification_code' => Str::upper(Str::random(16)),
        'certificate_type' => $certificateType->value,
        'resident_name' => 'Maria Santos',
        'purpose' => 'Employment requirement',
        'amount_paid' => $fee,
        'issued_at' => now(),
        'issued_by' => 'Records Officer',
        'qr_code_path' => '/storage/qrcodes/test.svg',
    ]);

    $this->actingAs($user)
        ->get(route('admin.issued-certificates.print', $certificate))
        ->assertOk()
        ->assertViewIs($expectedView)
        ->assertSeeText('Maria Santos')
        ->assertSeeText($certificate->certificate_number);
})->with('certificate types');

it('publicly verifies a real code through HTML and JSON regardless of letter case', function () {
    $certificate = IssuedCertificate::query()->create([
        'certificate_number' => 'CERT-2026-VERIFY',
        'verification_code' => 'VALIDVERIFYCODE1',
        'certificate_type' => CertificateType::BarangayClearance->value,
        'resident_name' => 'Maria Santos',
        'purpose' => 'Employment',
        'amount_paid' => 50,
        'issued_at' => '2026-09-16 08:30:00',
        'issued_by' => 'Records Officer',
    ]);

    $this->get(route('certificate.verify', strtolower($certificate->verification_code)))
        ->assertOk()
        ->assertViewIs('certificate.verify')
        ->assertSeeText('Authentic Barangay Document')
        ->assertSeeText('CERT-2026-VERIFY')
        ->assertSeeText('Maria Santos');
    $this->getJson(route('certificate.verify', strtolower($certificate->verification_code)))
        ->assertOk()
        ->assertJsonPath('certificate.certificate_number', 'CERT-2026-VERIFY')
        ->assertJsonPath('certificate.verification_code', 'VALIDVERIFYCODE1')
        ->assertJsonPath('certificate.resident_name', 'Maria Santos');
});

it('returns 404 for an unknown public verification code', function () {
    $this->getJson(route('certificate.verify', 'UNKNOWNCODE'))
        ->assertNotFound();
});

it('escapes resident-provided content on public verification and print pages', function () {
    $user = User::factory()->create();
    $dangerousName = "<script>alert('xss')</script>";
    $certificate = IssuedCertificate::query()->create([
        'certificate_number' => 'CERT-2026-ESCAPE',
        'verification_code' => 'ESCAPEVERIFYCODE',
        'certificate_type' => CertificateType::BarangayClearance->value,
        'resident_name' => $dangerousName,
        'purpose' => $dangerousName,
        'amount_paid' => 50,
        'issued_at' => now(),
    ]);

    $this->get(route('certificate.verify', $certificate->verification_code))
        ->assertOk()
        ->assertSee('&lt;script&gt;', false)
        ->assertDontSee($dangerousName, false);
    $this->actingAs($user)
        ->get(route('admin.issued-certificates.print', $certificate))
        ->assertOk()
        ->assertSee('&lt;script&gt;', false)
        ->assertDontSee($dangerousName, false);
});

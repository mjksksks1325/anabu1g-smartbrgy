<?php

use App\CertificateType;
use App\Models\DocumentRequest;
use App\Models\IssuedCertificate;
use Illuminate\Support\Facades\Storage;

test('expired issued certificates and stored files are removed while request history remains', function () {
    Storage::fake('local');
    Storage::fake('public');
    $this->travelTo('2026-10-20 12:00:00');

    $request = DocumentRequest::query()->create([
        'reference_code' => 'REQ-EXPIRED-CERT',
        'document_type' => CertificateType::BarangayClearance->value,
        'full_name' => 'Historical Resident',
        'address' => 'Anabu I-G',
        'status' => 'released',
    ]);
    $expiredCertificates = collect([
        IssuedCertificate::query()->create([
            'document_request_id' => $request->id,
            'certificate_number' => 'CERT-EXPIRED-ORDINARY',
            'verification_code' => 'EXPIREDORDINARY',
            'certificate_type' => CertificateType::BarangayClearance->value,
            'resident_name' => 'Historical Resident',
            'issued_at' => '2026-06-01 12:00:00',
            'expires_on' => '2026-09-01',
            'qr_code_path' => '/storage/qrcodes/expired-ordinary.svg',
            'photo_path' => 'certificate-photos/expired-ordinary.jpg',
        ]),
        IssuedCertificate::query()->create([
            'certificate_number' => 'CERT-EXPIRED-ID',
            'verification_code' => 'EXPIREDIDCERTCODE',
            'certificate_type' => CertificateType::BarangayId->value,
            'resident_name' => 'Historical ID Holder',
            'issued_at' => '2025-10-19 12:00:00',
            'expires_on' => '2026-10-19',
            'qr_code_path' => 'qrcodes/expired-id.svg',
        ]),
    ]);
    foreach ($expiredCertificates as $certificate) {
        if ($certificate->qr_code_path !== null) {
            Storage::disk('public')->put(str_replace('/storage/', '', $certificate->qr_code_path), 'qr snapshot');
        }
        if ($certificate->photo_path !== null) {
            Storage::disk('local')->put($certificate->photo_path, 'photo snapshot');
        }
    }

    $activeCertificate = IssuedCertificate::query()->create([
        'certificate_number' => 'CERT-STILL-VALID',
        'verification_code' => 'STILLVALIDCODE',
        'certificate_type' => CertificateType::BarangayClearance->value,
        'resident_name' => 'Current Resident',
        'issued_at' => '2026-10-19 12:00:00',
        'expires_on' => '2027-01-19',
    ]);
    $certificateValidThroughToday = IssuedCertificate::query()->create([
        'certificate_number' => 'CERT-VALID-THROUGH-TODAY',
        'verification_code' => 'VALIDTHROUGHTODAY',
        'certificate_type' => CertificateType::BarangayClearance->value,
        'resident_name' => 'Current Resident',
        'issued_at' => '2026-07-20 12:00:00',
        'expires_on' => '2026-10-20',
    ]);

    $this->artisan('certificates:purge-expired')->assertSuccessful();

    foreach ($expiredCertificates as $certificate) {
        $this->assertDatabaseMissing('issued_certificates', ['id' => $certificate->id]);
        $this->getJson(route('certificate.verify', $certificate->verification_code))->assertNotFound();
    }
    $this->assertDatabaseHas('document_requests', ['id' => $request->id, 'status' => 'released']);
    $this->assertDatabaseHas('issued_certificates', ['id' => $activeCertificate->id]);
    $this->assertDatabaseHas('issued_certificates', ['id' => $certificateValidThroughToday->id]);
    Storage::disk('public')->assertDirectoryEmpty('qrcodes');
    Storage::disk('local')->assertDirectoryEmpty('certificate-photos');
});

test('dry run reports expired certificates without deleting records or files', function () {
    Storage::fake('public');
    $this->travelTo('2026-10-20 12:00:00');
    $certificate = IssuedCertificate::query()->create([
        'certificate_number' => 'CERT-DRY-RUN',
        'verification_code' => 'DRYRUNVERIFYCODE',
        'certificate_type' => CertificateType::BarangayClearance->value,
        'resident_name' => 'Historical Resident',
        'issued_at' => '2026-06-01 12:00:00',
        'expires_on' => '2026-09-01',
        'qr_code_path' => '/storage/qrcodes/dry-run.svg',
    ]);
    Storage::disk('public')->put('qrcodes/dry-run.svg', 'qr snapshot');

    $this->artisan('certificates:purge-expired --dry-run')
        ->expectsOutput('Expired certificates that would be deleted: 1')
        ->assertSuccessful();

    $this->assertDatabaseHas('issued_certificates', ['id' => $certificate->id]);
    Storage::disk('public')->assertExists('qrcodes/dry-run.svg');
});

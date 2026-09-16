<?php

use App\CertificateType;
use App\Models\IssuedCertificate;

test('absolute QR URLs are normalized so printed images use the current application host', function () {
    $certificate = IssuedCertificate::query()->create([
        'certificate_number' => 'CERT-2026-OLDURL',
        'verification_code' => 'OLDURLVERIFYCODE',
        'certificate_type' => CertificateType::BarangayClearance->value,
        'resident_name' => 'Maria Santos',
        'amount_paid' => 50,
        'issued_at' => now(),
        'qr_code_path' => 'http://localhost:8000/storage/qrcodes/CERT-2026-OLDURL.svg',
    ]);
    $migration = require database_path(
        'migrations/2026_09_16_090605_normalize_issued_certificate_qr_code_paths.php'
    );

    $migration->up();

    expect($certificate->fresh()->qr_code_path)
        ->toBe('/storage/qrcodes/CERT-2026-OLDURL.svg');
});

test('legacy public QR paths remain unchanged', function () {
    $certificate = IssuedCertificate::query()->create([
        'certificate_number' => 'CERT-2026-LEGACY',
        'verification_code' => 'LEGACYVERIFYCODE',
        'certificate_type' => CertificateType::BarangayClearance->value,
        'resident_name' => 'Juan Dela Cruz',
        'amount_paid' => 50,
        'issued_at' => now(),
        'qr_code_path' => 'qrcodes/CERT-2026-LEGACY.svg',
    ]);
    $migration = require database_path(
        'migrations/2026_09_16_090605_normalize_issued_certificate_qr_code_paths.php'
    );

    $migration->up();

    expect($certificate->fresh()->qr_code_path)
        ->toBe('qrcodes/CERT-2026-LEGACY.svg');
});

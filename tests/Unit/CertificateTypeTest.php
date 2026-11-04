<?php

use App\CertificateType;

test('fee labels read as free or as formatted pesos', function () {
    expect(CertificateType::CertificateOfIndigency->feeLabel())->toBe('Walang bayad')
        ->and(CertificateType::BarangayClearance->feeLabel())->toBe('PHP 25.00')
        ->and(CertificateType::CertificateOfResidency->feeLabel())->toBe('PHP 25.00')
        ->and(CertificateType::FirstTimeJobseeker->feeLabel())->toBe('Walang bayad')
        ->and(CertificateType::BusinessClearance->feeLabel())->toBe('PHP 200.00');
});

test('Filipino mode shows each official document name exactly as printed on the certificate', function () {
    $filipino = json_decode(file_get_contents(dirname(__DIR__, 2).'/lang/fil.json'), true, flags: JSON_THROW_ON_ERROR);

    foreach (CertificateType::cases() as $type) {
        expect($filipino[$type->value] ?? $type->value)->toBe($type->value);
    }
});

test('the fee schedule lists every certificate and marks only the business clearance fee as unconfirmed', function () {
    $schedule = CertificateType::feeSchedule();

    expect(array_keys($schedule))->toBe(CertificateType::values())
        ->and($schedule['Barangay Clearance'])->toBe(['amount' => 25, 'confirmed' => true])
        ->and($schedule['Certificate of Indigency'])->toBe(['amount' => 0, 'confirmed' => true])
        ->and($schedule['Business Clearance'])->toBe(['amount' => 200, 'confirmed' => false])
        ->and(collect($schedule)->where('confirmed', false)->keys()->all())->toBe(['Business Clearance']);
});

<?php

use App\CertificateType;

test('fee labels read as free or as formatted pesos', function () {
    expect(CertificateType::CertificateOfIndigency->feeLabel())->toBe('Walang bayad')
        ->and(CertificateType::BarangayClearance->feeLabel())->toBe('PHP 25.00')
        ->and(CertificateType::CertificateOfResidency->feeLabel())->toBe('PHP 25.00')
        ->and(CertificateType::FirstTimeJobseeker->feeLabel())->toBe('Walang bayad')
        ->and(CertificateType::BusinessClearance->feeLabel())->toBe('PHP 200.00');
});

test('the fee schedule lists every certificate and marks only the business clearance fee as unconfirmed', function () {
    $schedule = CertificateType::feeSchedule();

    expect(array_keys($schedule))->toBe(CertificateType::values())
        ->and($schedule['Barangay Clearance'])->toBe(['amount' => 25, 'confirmed' => true])
        ->and($schedule['Certificate of Indigency'])->toBe(['amount' => 0, 'confirmed' => true])
        ->and($schedule['Business Clearance'])->toBe(['amount' => 200, 'confirmed' => false])
        ->and(collect($schedule)->where('confirmed', false)->keys()->all())->toBe(['Business Clearance']);
});

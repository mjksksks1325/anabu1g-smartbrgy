<?php

use App\CertificateType;

test('fee labels read as free or as formatted pesos', function () {
    expect(CertificateType::CertificateOfIndigency->feeLabel())->toBe('Walang bayad')
        ->and(CertificateType::BarangayClearance->feeLabel())->toBe('PHP 25.00')
        ->and(CertificateType::CertificateOfResidency->feeLabel())->toBe('PHP 25.00')
        ->and(CertificateType::FirstTimeJobseeker->feeLabel())->toBe('Walang bayad')
        ->and(CertificateType::BusinessClearance->feeLabel())->toBe('PHP 200.00');
});

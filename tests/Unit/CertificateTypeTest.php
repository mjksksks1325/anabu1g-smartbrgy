<?php

use App\CertificateType;

test('fee labels read as free or as formatted pesos', function () {
    expect(CertificateType::CertificateOfIndigency->feeLabel())->toBe('Walang bayad')
        ->and(CertificateType::BarangayClearance->feeLabel())->toBe('PHP 50.00')
        ->and(CertificateType::BusinessClearance->feeLabel())->toBe('PHP 200.00');
});

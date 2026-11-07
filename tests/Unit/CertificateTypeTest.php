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

test('first time jobseeker certifications and barangay IDs last one year and every other certificate three months', function () {
    expect(collect(CertificateType::cases())->mapWithKeys(fn (CertificateType $type): array => [$type->value => $type->validityPeriodInMonths()])->all())->toBe([
        'Barangay Clearance' => 3, 'Certificate of Residency' => 3, 'Certificate of Indigency' => 3,
        'Registered Voter Certification' => 3, 'Barangay ID' => 12, 'First Time Jobseeker' => 12, 'Business Clearance' => 3,
    ]);
});

test('each certificate names the official template it follows and the details that template prints', function () {
    expect(CertificateType::CertificateOfIndigency->officialReferences())->toBe(['Brgy. Indigency 2026.docx'])
        ->and(CertificateType::FirstTimeJobseeker->officialReferences())->toHaveCount(2)
        ->and(CertificateType::BarangayClearance->officialReferences())->toBe([])
        ->and(CertificateType::RegisteredVoterCertification->requiredDetails())->toContain('nationality', 'photo')
        ->and(CertificateType::CertificateOfResidency->staffEnteredDetails())->toBe(['residing_since'])
        ->and(CertificateType::FirstTimeJobseeker->staffEnteredDetails())->toBe(['years_of_residency'])
        ->and(CertificateType::BusinessClearance->staffEnteredDetails())->toBe([])
        ->and(CertificateType::detailLabel('photo'))->toBe('Resident photo');
});

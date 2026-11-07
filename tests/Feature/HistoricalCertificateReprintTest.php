<?php

use App\CertificateDocument;
use App\CertificateType;
use App\Models\IssuedCertificate;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A certificate as the system stored it before the official 2026 templates: details frozen at issuance, no template
 * version, none of the newer residency details.
 *
 * @param  array<string, mixed>  $attributes
 */
function historicalCertificate(CertificateType $type, array $attributes = []): IssuedCertificate
{
    return IssuedCertificate::query()->create([
        'certificate_number' => 'CERT-2026-HIST'.fake()->unique()->numerify('####'), 'verification_code' => 'HIST'.fake()->unique()->numerify('############'),
        'certificate_type' => $type->value, 'resident_name' => 'Historical Resident', 'purpose' => 'Historical purpose',
        'issued_at' => '2026-10-20 10:00:00', 'expires_on' => '2027-01-20', 'issued_by' => 'Former Clerk',
        'qr_code_path' => '/storage/qrcodes/historical.svg',
        'resident_snapshot' => ['full_name' => 'Historical Resident', 'address' => 'Historical address', 'date_of_birth' => '1980-05-01', 'gender' => 'Male', 'civil_status' => 'Married', 'nationality' => null],
        ...$attributes,
    ]);
}

test('a certificate issued before the 2026 templates reprints with the wording and details recorded at issuance', function (CertificateType $type, array $wording, array $absent) {
    $staff = User::factory()->assignedOperations()->create();
    $certificate = historicalCertificate($type);

    $print = $this->actingAs($staff)->get(route('staff.issued-certificates.print', $certificate))
        ->assertOk()->assertViewIs('admin.certificates.historical')
        ->assertSeeInOrder(['FULL:', 'Historical Resident', 'ADDRESS:', 'Historical address', 'DATE OF BIRTH:', 'May 01, 1980', 'GENDER:', 'Male', 'CIVIL STATUS:', 'Married', 'NATIONALITY:', 'PURPOSE:', 'Historical purpose', 'DATE ISSUE:', 'October 20, 2026', 'EXPIRATION DATE:', 'January 20, 2027'])
        ->assertSeeText('Issued this 20th day of October, 2026 at the office of Barangay Anabu I-G, City of Imus, Cavite.')
        ->assertSeeText('not recorded then, so left blank as on the original: Nationality')
        ->assertSee('Certificate authenticity QR code')->assertDontSee('official-placeholder.svg')->assertDontSee('Resident photo');
    foreach ($wording as $text) {
        $print->assertSeeText($text);
    }
    foreach ($absent as $text) {
        $print->assertDontSeeText($text);
    }
    $print->assertSee('.historical .resident-signature { margin-top: 12.7mm; }', false)
        ->assertSee('.historical.fit-tight .resident-signature { margin-top: 7mm; }', false)
        ->assertDontSee('data-reference-top="', false);

    $this->assertDatabaseHas('administrative_audits', ['user_id' => $staff->id, 'action' => 'admin.issued-certificates.historical-reprint', 'type' => 'cert', 'record' => (string) $certificate->id]);
    expect($certificate->fresh()->only(['certificate_number', 'issued_at', 'resident_snapshot', 'template_version']))->toEqual($certificate->only(['certificate_number', 'issued_at', 'resident_snapshot', 'template_version']));
})->with([
    'registered voter' => [CertificateType::RegisteredVoterCertification, ['To Whom it may concern;', 'herein is a registered voter of this barangay.', 'Right Thumb Mark', 'Signature over Printed Name'], ['INDIGENT', 'PUNONG BARANGAY']],
    'indigency' => [CertificateType::CertificateOfIndigency, ['CERTIFICATION OF INDIGENCY', 'herein is a bona fide resident and registered voter of this barangay.', 'belongs to an INDIGENT family of this barangay.'], ['Right Thumb Mark', 'PUNONG BARANGAY']],
    'barangay clearance' => [CertificateType::BarangayClearance, ['BARANGAY CLEARANCE', 'based on the records available in this Barangay', 'PUNONG BARANGAY'], ['Right Thumb Mark', 'registered voter']],
    'residency' => [CertificateType::CertificateOfResidency, ['CERTIFICATE OF RESIDENCY', 'This certification is issued upon the request of the above-named person for Historical purpose.'], ['since', 'Mr./ Mrs.']],
    'first time jobseeker' => [CertificateType::FirstTimeJobseeker, ["This certification is issued in connection with the resident's application as a first-time jobseeker"], ['OATH OF UNDERTAKING', 'RA 11261']],
]);

test('a certificate issued before details were recorded is not reconstructed: staff review the record instead', function () {
    $staff = User::factory()->assignedOperations()->create();
    $certificate = historicalCertificate(CertificateType::CertificateOfIndigency, ['resident_snapshot' => null, 'expires_on' => null]);

    $this->actingAs($staff)->get(route('staff.issued-certificates.print', $certificate))
        ->assertStatus(409)->assertViewIs('admin.certificates.unavailable')
        ->assertSeeText('This certificate cannot be reprinted')
        ->assertSeeInOrder([$certificate->certificate_number, 'Certificate of Indigency', 'Historical Resident', 'Historical purpose', 'October 20, 2026', 'Former Clerk'])
        ->assertDontSee('Print certificate')->assertDontSeeText('INDIGENT family');

    $this->assertDatabaseMissing('administrative_audits', ['action' => 'admin.issued-certificates.historical-reprint']);
});

test('historical certificates keep the validity rule in force when they were issued', function () {
    $this->travelTo('2026-11-07 09:00:00');
    $older = historicalCertificate(CertificateType::FirstTimeJobseeker, ['issued_at' => '2026-09-01 09:00:00', 'expires_on' => null, 'resident_snapshot' => null]);
    $current = historicalCertificate(CertificateType::FirstTimeJobseeker, ['issued_at' => '2026-09-01 09:00:00', 'expires_on' => null, 'template_version' => CertificateDocument::TEMPLATE_VERSION]);
    $identification = historicalCertificate(CertificateType::BarangayId, ['issued_at' => '2026-09-01 09:00:00', 'expires_on' => null]);

    expect($older->validUntil()?->toDateString())->toBe('2026-12-01')
        ->and($current->validUntil()?->toDateString())->toBe('2027-09-01')
        ->and($identification->validUntil()?->toDateString())->toBe('2027-09-01');
});

test('new certificates record the template version they were issued with', function () {
    Storage::fake('public');
    $resident = Resident::factory()->create(['date_of_birth' => '1990-01-01']);

    $this->actingAs(User::factory()->assignedOperations()->create())->postJson(route('staff.issued-certificates.store'), [
        'certificate_type' => CertificateType::BarangayClearance->value, 'resident_id' => $resident->id,
        'resident_name' => $resident->full_name, 'address' => $resident->address, 'purpose' => 'Employment',
    ])->assertOk();

    expect(IssuedCertificate::query()->sole()->template_version)->toBe(CertificateDocument::TEMPLATE_VERSION);
});

test('the migration marks only certificates issued with the 2026 templates', function () {
    $migration = require database_path('migrations/2026_11_07_125144_add_template_version_and_revocation_to_issued_certificates_table.php');
    $older = historicalCertificate(CertificateType::BarangayClearance);
    $current = historicalCertificate(CertificateType::BarangayClearance, ['resident_snapshot' => completeCertificateSnapshot()]);
    $migration->down();
    $migration->up();

    expect(DB::table('issued_certificates')->where('id', $older->id)->value('template_version'))->toBeNull()
        ->and(DB::table('issued_certificates')->where('id', $current->id)->value('template_version'))->toBe(CertificateDocument::TEMPLATE_VERSION);
});

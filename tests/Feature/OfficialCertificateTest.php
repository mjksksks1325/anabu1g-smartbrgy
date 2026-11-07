<?php

use App\Actions\IssueCertificate;
use App\CertificateDocument;
use App\CertificateType;
use App\Models\DocumentRequest;
use App\Models\IssuedCertificate;
use App\Models\Resident;
use App\Models\User;
use App\Models\VoterRegistration;
use App\PortalLocalization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('official certificates preserve all historical details and private photos through the existing request flow', function (CertificateType $type) {
    Storage::fake('local');
    Storage::fake('public');
    $this->travelTo(now()->setDate(2026, 10, 3)->startOfDay());
    $resident = Resident::factory()->create([
        'first_name' => 'Test', 'middle_name' => null, 'last_name' => 'Resident', 'suffix' => null,
        'address' => 'Example Street, Anabu I-G', 'date_of_birth' => '1990-02-12',
        'gender' => 'Female', 'civil_status' => 'Single', 'nationality' => 'Test nationality',
        'is_verified_indigent' => true, 'is_in_good_standing' => true,
    ]);
    VoterRegistration::factory()->create(['resident_id' => $resident->id, 'status' => 'active']);
    $resident->update(['photo_path' => UploadedFile::fake()->image('resident.jpg')->store('resident-photos', 'local')]);
    $account = User::factory()->resident()->create(['resident_id' => $resident->id]);
    $this->actingAs($account, 'resident')->postJson(route('portal.request.store'), [
        'document_type' => $type->value, 'purpose' => 'Test assistance',
    ])->assertOk();
    $request = DocumentRequest::query()->sole();
    $staff = User::factory()->assignedOperations()->create();
    $this->actingAs($staff, 'web')->patchJson(route('staff.document-requests.update-status', $request), ['status' => 'processing'])->assertOk();
    $this->patchJson(route('staff.document-requests.update-status', $request), ['status' => 'ready_for_release'])->assertOk();
    $this->actingAs($staff, 'web')->postJson(route('staff.document-requests.issue', $request), ['expires_on' => '2026-12-31'])
        ->assertOk()->assertJsonPath('certificate.certificate_type', $type->value);
    $certificate = IssuedCertificate::query()->sole();
    expect($request->fresh()->status)->toBe('released');
    Storage::disk('local')->assertExists($certificate->photo_path);
    Storage::disk('public')->assertExists(str_replace('/storage/', '', $certificate->qr_code_path));
    expect($certificate->photo_path)->not->toBe($resident->photo_path);
    $originalPhoto = Storage::disk('local')->get($certificate->photo_path);
    $resident->update(['first_name' => 'Changed', 'address' => 'Changed address', 'nationality' => null, 'date_of_birth' => '1980-01-01', 'gender' => 'Male', 'civil_status' => 'Married']);
    $this->postJson(route('staff.residents.photo.store', $resident), ['photo' => UploadedFile::fake()->image('replacement.png')])->assertOk();
    expect(Storage::disk('local')->get($certificate->photo_path))->toBe($originalPhoto);
    $print = $this->get(route('staff.issued-certificates.print', $certificate))->assertOk();
    expect($certificate->expires_on?->toDateString())->toBe('2027-01-03');
    foreach (['Province of Cavite', 'OFFICE OF THE SANGGUNIANG BARANGAY', 'Test Resident', 'Example Street, Anabu I-G', 'February 12, 1990', 'Female', 'Single', 'Test nationality', 'Test assistance', 'October 03, 2026', 'January 03, 2027', 'EXPIRATION DATE', 'Resident photo at issuance', 'registered voter'] as $text) {
        $print->assertSee($text);
    }
    $print->assertDontSee('Changed address')->assertSee(route('staff.issued-certificates.photo', $certificate));
    $print->assertDontSeeText('Certificate No.:')->assertDontSeeText('Verification code:')
        ->assertDontSeeText($certificate->certificate_number)->assertDontSeeText($certificate->verification_code)
        ->assertSee(asset($certificate->qr_code_path));
    $print->assertSee(asset('images/certificates/anabu-source-seal.png'))
        ->assertSeeInOrder(['Resident photo at issuance', 'To Whom it may concern', 'FULL:', 'PURPOSE:', 'DATE ISSUE:', 'EXPIRATION DATE:', 'Issued this', 'Scan QR code to verify', 'City of Imus footer logo']);
    if ($type === CertificateType::RegisteredVoterCertification) {
        $print->assertSeeInOrder(['Right Thumb Mark', 'Signature over Printed Name', 'Scan QR code to verify']);
    } else {
        $print->assertSeeText('INDIGENT family')->assertDontSee('Right Thumb Mark');
    }
    $this->get(route('staff.issued-certificates.photo', $certificate))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->postJson(route('staff.document-requests.issue', $request))->assertConflict();
    $verification = $this->getJson(route('certificate.verify', $certificate->verification_code))->assertOk();
    expect(array_keys($verification->json('certificate')))->toBe(['certificate_number', 'verification_code', 'certificate_type', 'resident_name', 'purpose', 'issued_at', 'issued_by', 'expires_on']);
    $this->getJson(route('certificate.verify', 'INVALID-CODE'))->assertNotFound();
    $other = User::factory()->resident()->create();
    $this->actingAs($other, 'web')->get(route('staff.issued-certificates.print', $certificate))->assertForbidden();
    $this->get(route('staff.issued-certificates.photo', $certificate))->assertForbidden();
    $this->actingAs($other, 'resident')->getJson(route('portal.request.status', $request->reference_code))->assertNotFound();
})->with([CertificateType::RegisteredVoterCertification, CertificateType::CertificateOfIndigency]);

test('a certificate is not issued while the resident record lacks a detail its template prints', function () {
    Storage::fake('local');
    Storage::fake('public');
    $resident = Resident::factory()->create(['is_in_good_standing' => true, 'nationality' => null]);
    VoterRegistration::factory()->create(['resident_id' => $resident->id]);

    $this->actingAs(User::factory()->assignedOperations()->create())->postJson(route('staff.issued-certificates.store'), [
        'certificate_type' => CertificateType::RegisteredVoterCertification->value,
        'resident_id' => $resident->id, 'resident_name' => $resident->full_name, 'address' => $resident->address,
    ])->assertUnprocessable()->assertJsonPath('message', 'Complete these details before issuing this certificate: Nationality, Purpose, Resident photo. Update the resident record in Resident Records first.');

    $this->postJson(route('staff.issued-certificates.store'), [
        'certificate_type' => CertificateType::CertificateOfResidency->value,
        'resident_name' => 'Walk-in Resident', 'address' => 'Anabu I-G', 'purpose' => 'Employment', 'residing_since' => '2000-01',
    ])->assertUnprocessable()->assertJsonPath('message', 'Complete these details before issuing this certificate: Date of birth, Civil status. Select the linked resident record so these details come from Resident Records.');

    $this->assertDatabaseEmpty('issued_certificates');
    $this->assertDatabaseEmpty('document_requests');
    Storage::disk('public')->assertDirectoryEmpty('qrcodes');
});

test('residency and first time jobseeker certificates need the residency detail staff confirm, within the resident\'s lifetime', function (CertificateType $type, array $details, string $message) {
    Storage::fake('public');
    $resident = Resident::factory()->create(['date_of_birth' => '2000-05-10', 'is_in_good_standing' => true]);
    $this->travelTo('2026-10-03 09:00:00');

    $this->actingAs(User::factory()->assignedOperations()->create())->postJson(route('staff.issued-certificates.store'), [
        'certificate_type' => $type->value, 'resident_id' => $resident->id, 'resident_name' => $resident->full_name,
        'address' => $resident->address, 'purpose' => 'Employment', ...$details,
    ])->assertUnprocessable()->assertJsonPath('message', $message);

    $this->assertDatabaseEmpty('issued_certificates');
})->with([
    'no resident since month' => [CertificateType::CertificateOfResidency, [], 'Complete these details before issuing this certificate: Resident since (month and year).'],
    'resident since in the future' => [CertificateType::CertificateOfResidency, ['residing_since' => '2026-11'], 'Resident since cannot be later than this month.'],
    'resident since before birth' => [CertificateType::CertificateOfResidency, ['residing_since' => '2000-04'], "Resident since cannot be earlier than the resident's date of birth."],
    'no years of residency' => [CertificateType::FirstTimeJobseeker, [], 'Complete these details before issuing this certificate: Years of residency in Anabu I-G.'],
    'more years than the resident has lived' => [CertificateType::FirstTimeJobseeker, ['years_of_residency' => 27], "Years of residency cannot be more than the resident's age (26)."],
]);

test('staff-entered residency details must be a month and a whole number of years', function () {
    $request = DocumentRequest::query()->create([
        'reference_code' => 'REQ-DETAILS-1', 'document_type' => CertificateType::CertificateOfResidency->value,
        'full_name' => 'Juan Dela Cruz', 'address' => 'Anabu I-G', 'purpose' => 'Employment', 'status' => 'ready_for_release',
    ]);

    $this->actingAs(User::factory()->assignedOperations()->create())
        ->postJson(route('staff.document-requests.issue', $request), ['residing_since' => '2026-13-01', 'years_of_residency' => 'five'])
        ->assertJsonValidationErrors(['residing_since', 'years_of_residency']);
    $this->postJson(route('staff.issued-certificates.store'), [
        'certificate_type' => CertificateType::FirstTimeJobseeker->value, 'resident_name' => 'A', 'address' => 'B', 'years_of_residency' => -1,
    ])->assertJsonValidationErrors('years_of_residency');

    $this->assertDatabaseEmpty('issued_certificates');
});

test('barangay IDs remain valid for one year and their QR verification expires after that date', function () {
    Storage::fake('public');
    $this->travelTo('2026-10-03 09:00:00');
    $staff = User::factory()->assignedOperations()->create();

    $this->actingAs($staff)->postJson(route('staff.issued-certificates.store'), [
        'certificate_type' => CertificateType::BarangayId->value,
        'resident_name' => 'Test Resident',
        'address' => 'Anabu I-G',
    ])->assertOk();

    $certificate = IssuedCertificate::query()->sole();
    expect($certificate->expires_on?->toDateString())->toBe('2027-10-03')
        ->and($certificate->validUntil()?->toDateString())->toBe('2027-10-03');
    $this->get(route('staff.issued-certificates.print', $certificate))
        ->assertOk()->assertSeeText('Oct 03, 2027');

    $this->travelTo('2027-10-03 23:59:00');
    $this->getJson(route('certificate.verify', $certificate->verification_code))->assertOk();

    $this->travelTo('2027-10-04 00:00:00');
    $this->getJson(route('certificate.verify', $certificate->verification_code))
        ->assertStatus(410)->assertJsonPath('status', 'expired');
});

test('resident request terms give the three month certificate and one year First Time Jobseeker and Barangay ID validity in both languages', function (string $locale, string $expected) {
    $account = User::factory()->resident()->create();

    $this->withCookie(PortalLocalization::COOKIE, $locale)
        ->actingAs($account, 'resident')
        ->get(route('portal.request.create'))
        ->assertOk()
        ->assertSeeText($expected);
})->with([
    'English' => ['en', 'Issued certificates are valid for three months from issue; First Time Jobseeker certificates and Barangay IDs are valid for one year.'],
    'Filipino' => ['fil', 'May bisa nang tatlong buwan mula sa pag-isyu ang mga sertipiko, at isang taon naman ang First Time Jobseeker certificate at ang Barangay ID.'],
]);

test('official certificate assertions require verified linked records', function (string $condition) {
    Storage::fake('public');
    $resident = Resident::factory()->create(['is_in_good_standing' => true, 'is_verified_indigent' => true]);
    $voter = VoterRegistration::factory()->create(['resident_id' => $resident->id]);
    if ($condition === 'not indigent') {
        $resident->update(['is_verified_indigent' => false]);
    } elseif ($condition === 'inactive voter') {
        $voter->update(['status' => 'transferred']);
    } elseif ($condition === 'tampered voter') {
        DB::table('voter_registrations')->where('id', $voter->id)->update(['integrity_hash' => 'tampered']);
    } elseif ($condition === 'missing voter') {
        $voter->delete();
    } elseif ($condition === 'not good standing') {
        $resident->update(['is_in_good_standing' => false]);
    } elseif ($condition === 'inactive resident') {
        $resident->update(['status' => 'inactive']);
    }
    $this->actingAs(User::factory()->assignedOperations()->create())->postJson(route('staff.issued-certificates.store'), [
        'certificate_type' => CertificateType::CertificateOfIndigency->value,
        'resident_id' => $condition === 'unlinked' ? null : $resident->id,
        'resident_name' => $resident->full_name, 'address' => $resident->address,
    ])->assertUnprocessable();
    $this->assertDatabaseEmpty('issued_certificates');
    $this->assertDatabaseEmpty('document_requests');
})->with(['not indigent', 'inactive voter', 'tampered voter', 'missing voter', 'not good standing', 'inactive resident', 'unlinked']);

test('staff cannot override the three month certificate validity period', function () {
    Storage::fake('public');
    $this->travelTo('2026-10-03 09:00:00');
    $this->actingAs(User::factory()->assignedOperations()->create())->postJson(route('staff.issued-certificates.store'), [
        'certificate_type' => CertificateType::BarangayClearance->value,
        'resident_name' => 'Test Resident', 'address' => 'Test address', 'purpose' => 'Employment', 'expires_on' => '2030-01-01',
    ])->assertOk();
    $certificate = IssuedCertificate::query()->sole();

    expect($certificate->expires_on?->toDateString())->toBe('2027-01-03');
});

test('certificate QR verification expires after three months and hides certificate details', function () {
    $this->travelTo('2026-10-03 09:00:00');
    $certificate = IssuedCertificate::query()->create([
        'certificate_number' => 'CERT-EXPIRY-TEST',
        'verification_code' => 'EXPIRYTESTCODE',
        'certificate_type' => CertificateType::BarangayClearance->value,
        'resident_name' => 'Private Verification Name',
        'issued_at' => now(),
        'expires_on' => '2030-01-01',
    ]);

    $this->travelTo('2027-01-03 23:59:00');
    $this->getJson(route('certificate.verify', $certificate->verification_code))
        ->assertOk()
        ->assertJsonPath('certificate.expires_on', '2027-01-03');

    $this->travelTo('2027-01-04 00:00:00');
    $this->getJson(route('certificate.verify', $certificate->verification_code))
        ->assertStatus(410)
        ->assertJsonPath('status', 'expired')
        ->assertDontSee('Private Verification Name');
    $this->get(route('certificate.verify', $certificate->verification_code))
        ->assertStatus(410)
        ->assertSee('Expired Barangay Document')
        ->assertDontSee('Private Verification Name');
});

test('registered voter certificates reject unverified voter records before issuing', function (string $condition) {
    Storage::fake('public');
    $resident = Resident::factory()->create(['is_in_good_standing' => true]);
    if ($condition !== 'missing') {
        VoterRegistration::factory()->create(['resident_id' => $resident->id, 'status' => 'deregistered']);
    }
    $this->actingAs(User::factory()->assignedOperations()->create())->postJson(route('staff.issued-certificates.store'), [
        'certificate_type' => CertificateType::RegisteredVoterCertification->value,
        'resident_id' => $resident->id, 'resident_name' => $resident->full_name, 'address' => $resident->address,
    ])->assertUnprocessable();
    $this->assertDatabaseEmpty('issued_certificates');
})->with(['missing', 'deregistered']);

test('failed persistence cleans up certificate photo and QR snapshots without losing the resident photo', function () {
    Storage::fake('local');
    Storage::fake('public');
    $resident = Resident::factory()->create(['date_of_birth' => '1990-01-01']);
    $photo = UploadedFile::fake()->image('resident.jpg')->store('resident-photos', 'local');
    $resident->update(['photo_path' => $photo]);
    IssuedCertificate::creating(function (): void {
        throw new RuntimeException('Simulated certificate persistence failure.');
    });
    expect(fn () => app(IssueCertificate::class)->handle([
        'certificate_type' => CertificateType::CertificateOfResidency->value,
        'resident_id' => $resident->id, 'resident_name' => $resident->full_name, 'address' => $resident->address,
        'purpose' => 'Employment', 'residing_since' => '2000-01',
    ], null, 'Test staff'))->toThrow(RuntimeException::class, 'Simulated certificate persistence failure.');
    Storage::disk('local')->assertExists($photo);
    Storage::disk('local')->assertDirectoryEmpty('certificate-photos');
    Storage::disk('public')->assertDirectoryEmpty('qrcodes');
    $this->assertDatabaseEmpty('issued_certificates');
    $this->assertDatabaseEmpty('document_requests');
});

test('official print fields escape resident supplied content', function () {
    $input = '<script>alert("test")</script>';
    $certificate = IssuedCertificate::query()->create([
        'certificate_number' => 'CERT-TEST-ESCAPING', 'verification_code' => 'TESTOFFICIALVERIFY',
        'certificate_type' => CertificateType::RegisteredVoterCertification->value,
        'resident_name' => $input, 'purpose' => $input, 'issued_at' => now(), 'photo_path' => 'certificate-photos/test.jpg', 'template_version' => CertificateDocument::TEMPLATE_VERSION,
        'resident_snapshot' => completeCertificateSnapshot(['full_name' => $input, 'first_name' => $input, 'address' => $input, 'nationality' => $input]),
    ]);
    $this->actingAs(User::factory()->assignedOperations()->create())->get(route('staff.issued-certificates.print', $certificate))
        ->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee($input, false);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function printableCertificate(CertificateType $type, array $attributes = []): IssuedCertificate
{
    return IssuedCertificate::query()->create([
        'certificate_number' => 'CERT-PRINT-'.Str::upper(Str::random(6)), 'verification_code' => 'PRINT'.Str::upper(Str::random(11)),
        'certificate_type' => $type->value, 'resident_name' => 'Test Resident', 'purpose' => 'Test purpose',
        'issued_at' => '2026-10-03 09:00:00', 'qr_code_path' => '/storage/qrcodes/print-test.svg',
        'photo_path' => 'certificate-photos/print-test.jpg', 'resident_snapshot' => completeCertificateSnapshot(), 'template_version' => CertificateDocument::TEMPLATE_VERSION,
        ...$attributes,
    ]);
}

test('indigency and bona fide resident certificates print the official template wording and details', function (CertificateType $type, array $wording, array $absent) {
    $certificate = printableCertificate($type);

    $print = $this->actingAs(User::factory()->assignedOperations()->create())->get(route('staff.issued-certificates.print', $certificate))
        ->assertOk()->assertSee(route('staff.issued-certificates.photo', $certificate))
        ->assertSeeInOrder(['FULL:', 'Test Resident', 'ADDRESS:', 'Historical test address', 'DATE OF BIRTH:', 'February 12, 1990', 'GENDER:', 'Female', 'CIVIL STATUS:', 'Single', 'NATIONALITY:', 'Test nationality', 'PURPOSE:', 'Test purpose', 'DATE ISSUE:', 'October 03, 2026', 'EXPIRATION DATE:', 'January 03, 2027'])
        ->assertSeeText('Issued this 03rd day of October, 2026 at the office of Barangay Anabu I-G, City of Imus, Cavite.')
        ->assertDontSee('official-placeholder.svg')->assertDontSeeText($certificate->verification_code);
    foreach ($wording as $text) {
        $print->assertSeeText($text);
    }
    foreach ($absent as $text) {
        $print->assertDontSeeText($text);
    }
})->with([
    'indigency (Brgy. Indigency 2026)' => [CertificateType::CertificateOfIndigency, [
        'CERTIFICATION OF INDIGENCY', 'To Whom it may concern:',
        'This is to certify that the person whose photo, signature and thumb mark appear herein is a registered voter of this barangay. He/ She is of good moral character, a law-abiding citizen and has no derogatory record on file as of this date.',
        'This is to certify further that the above-named belongs to an INDIGENT family of this barangay.',
    ], ['bona fide resident and registered voter', 'Right Thumb Mark']],
    'bona fide resident and registered voter' => [CertificateType::RegisteredVoterCertification, [
        'To Whom it may concern;',
        'This is to certify that the person whose photo, signature and thumb mark appear herein is a bona fide resident and registered voter of this barangay.',
        'Right Thumb Mark', 'Test M. Resident', 'Signature over Printed Name',
    ], ['INDIGENT family']],
]);

test('residency follows the official one-sentence template and names a registered voter only when that was verified', function (bool $voterVerified, string $statement) {
    $certificate = printableCertificate(CertificateType::CertificateOfResidency, [
        'photo_path' => null, 'resident_snapshot' => completeCertificateSnapshot(['voter_verified' => $voterVerified]),
    ]);

    $this->actingAs(User::factory()->assignedOperations()->create())->get(route('staff.issued-certificates.print', $certificate))
        ->assertOk()
        ->assertSeeText("This is to certify that Mr./ Mrs. Test Resident, 36 years old, Single, {$statement} of Historical test address Barangay Anabu I-G, City of Imus Cavite since July 2001.")
        ->assertSeeText('This Certification is issued upon request of the above-mentioned person for Test purpose purposes it may serve.')
        ->assertDontSeeText('Right Thumb Mark')->assertDontSee('Resident photo');
})->with([
    'voter registration not verified' => [false, 'a bona fide resident'],
    'verified voter registration' => [true, 'a bona fide resident and registered voter'],
]);

test('first time jobseekers get the RA 11261 certification and the oath of undertaking, valid for one year', function () {
    $certificate = printableCertificate(CertificateType::FirstTimeJobseeker, ['purpose' => null, 'photo_path' => null]);

    $print = $this->actingAs(User::factory()->assignedOperations()->create())->get(route('staff.issued-certificates.print', $certificate))
        ->assertOk()
        ->assertSeeInOrder(['BARANGAY CERTIFICATION', '(First Time Jobseekers Assistance Act- RA 11261)', 'OATH OF UNDERTAKING', 'First Time Jobseeker'])
        ->assertSeeText('This is to certify that Mr./Ms. Test Resident, a resident of Historical test address, Barangay Anabu I-G, City of Imus, Cavite for 18 years is a qualified availee of RA 11261 or the First Time Jobseekers Act of 2019.')
        ->assertSeeText('This Certification is valid only October 03, 2027. (one (1) year from issuance).')
        ->assertSeeText('Sec. Pamela R. Abas')->assertSeeText('Barangay Secretary')
        ->assertSeeText('I, Test Resident, 36 years of age, resident of Historical test address, Barangay Anabu I-G, City of Imus, Cavite for 18 years.')
        ->assertSeeText('That I can avail the benefits of the law only once;');
    expect(substr_count($print->getContent(), 'Certificate authenticity QR code'))->toBe(1)
        ->and(substr_count($print->getContent(), '<section class="sheet'))->toBe(2);
});

test('first time jobseeker certificates and their QR verification stay valid for one year', function () {
    Storage::fake('public');
    $this->travelTo('2026-10-03 09:00:00');
    $resident = Resident::factory()->create(['date_of_birth' => '2006-01-15']);

    $this->actingAs(User::factory()->assignedOperations()->create())->postJson(route('staff.issued-certificates.store'), [
        'certificate_type' => CertificateType::FirstTimeJobseeker->value, 'resident_id' => $resident->id,
        'resident_name' => $resident->full_name, 'address' => $resident->address, 'years_of_residency' => 20,
    ])->assertOk();
    $certificate = IssuedCertificate::query()->sole();

    expect($certificate->expires_on?->toDateString())->toBe('2027-10-03')
        ->and($certificate->resident_snapshot['years_of_residency'])->toBe(20)
        ->and((float) $certificate->amount_paid)->toBe(0.0);
    $this->travelTo('2027-10-03 23:59:00');
    $this->getJson(route('certificate.verify', $certificate->verification_code))->assertOk();
    $this->travelTo('2027-10-04 00:00:00');
    $this->getJson(route('certificate.verify', $certificate->verification_code))->assertStatus(410);
});

test('clearances keep their wording in the official letterhead and print only the details on record', function (CertificateType $type, string $wording) {
    $certificate = printableCertificate($type, ['photo_path' => null, 'resident_snapshot' => ['full_name' => 'Walk-in Resident', 'address' => 'Walk-in address']]);

    $this->actingAs(User::factory()->assignedOperations()->create())->get(route('staff.issued-certificates.print', $certificate))
        ->assertOk()->assertSeeText($wording)->assertSeeInOrder(['FULL:', 'ADDRESS:', 'Walk-in address', 'PURPOSE:', 'Test purpose', 'DATE ISSUE:', 'EXPIRATION DATE:'])
        ->assertSeeText('Hon. Robinson P. Silla')->assertSeeText('Punong Barangay')
        ->assertSee('Certificate authenticity QR code')->assertSee('City of Imus footer logo')
        ->assertDontSeeText('DATE OF BIRTH:')->assertDontSeeText('NATIONALITY:')->assertDontSee('Resident photo')->assertDontSee('official-placeholder.svg')
        ->assertDontSeeText('registered voter')->assertDontSeeText('Right Thumb Mark')->assertDontSeeText($certificate->verification_code);
})->with([
    [CertificateType::BarangayClearance, 'based on the records available in this Barangay'],
    [CertificateType::BusinessClearance, 'has been issued this Barangay Business Clearance'],
]);

test('a certificate whose record lacks a detail its template prints is never printed with blanks', function () {
    $legacy = printableCertificate(CertificateType::CertificateOfIndigency, ['resident_name' => 'Historical Resident', 'photo_path' => null, 'resident_snapshot' => ['full_name' => 'Historical Resident']]);

    $this->actingAs(User::factory()->assignedOperations()->create())->get(route('staff.issued-certificates.print', $legacy))
        ->assertStatus(409)->assertViewIs('admin.certificates.incomplete')
        ->assertSeeText('This certificate cannot be printed')
        ->assertSeeInOrder(['Address', 'Date of birth', 'Gender', 'Civil status', 'Nationality', 'Resident photo'])
        ->assertDontSeeText('INDIGENT')->assertDontSee('Print certificate')->assertDontSee('Historical Resident');
});

test('the print page refuses to print a sheet whose text runs into the logos, photo or signature areas', function () {
    $print = $this->actingAs(User::factory()->assignedOperations()->create())
        ->get(route('staff.issued-certificates.print', printableCertificate(CertificateType::RegisteredVoterCertification)))->assertOk();

    $print->assertSee('@page { size: A4 portrait; margin: 0; }', false)
        ->assertSee('data-keep-clear', false)->assertSee('data-print-certificate', false)
        ->assertSee("document.body.dataset.printable = 'false'", false)
        ->assertSee('body[data-printable="false"] .sheets { display: none; }', false);
});

test('the ID card reuses its private photo while keeping its identification fields', function () {
    $certificate = IssuedCertificate::query()->create([
        'certificate_number' => 'CERT-ID-TEST', 'verification_code' => 'IDPRINTVERIFY',
        'certificate_type' => CertificateType::BarangayId->value,
        'resident_name' => 'Test Resident', 'issued_at' => now(), 'photo_path' => 'certificate-photos/test.jpg',
        'qr_code_path' => '/storage/qrcodes/id-test.svg',
    ]);
    $this->actingAs(User::factory()->assignedOperations()->create())->get(route('staff.issued-certificates.print', $certificate))
        ->assertOk()->assertSee('BARANGAY RESIDENT IDENTIFICATION CARD')->assertSeeText('ID No.:')->assertSeeText('CERT-ID-TEST')
        ->assertSeeText('Valid Until:')->assertSeeText($certificate->validUntil()?->format('M d, Y'))
        ->assertSee(route('staff.issued-certificates.photo', $certificate))->assertSee('Resident photo at issuance')
        ->assertSee('City of Imus footer logo')->assertSee('Certificate authenticity QR code')->assertDontSeeText('IDPRINTVERIFY');
});

test('an ID card without a photo on record shows an empty frame, not a stand-in avatar', function () {
    $certificate = IssuedCertificate::query()->create([
        'certificate_number' => 'CERT-ID-NOPHOTO', 'verification_code' => 'IDNOPHOTOVERIFY',
        'certificate_type' => CertificateType::BarangayId->value, 'resident_name' => 'Test Resident', 'issued_at' => now(),
    ]);

    $this->actingAs(User::factory()->assignedOperations()->create())->get(route('staff.issued-certificates.print', $certificate))
        ->assertOk()->assertSeeText('No photo on file')->assertDontSee('official-placeholder.svg')->assertDontSee('Resident photo at issuance');
});

test('certificate QR verification shows an authentic result with its details in the resident portal layout', function () {
    $this->travelTo('2026-10-03 09:00:00');
    $certificate = IssuedCertificate::query()->create([
        'certificate_number' => 'CERT-AUTHENTIC-TEST',
        'verification_code' => 'AUTHENTICTESTCODE',
        'certificate_type' => CertificateType::CertificateOfResidency->value,
        'resident_name' => 'Verified Resident Name',
        'issued_at' => now(),
        'expires_on' => '2030-01-01',
    ]);

    $this->get(route('certificate.verify', $certificate->verification_code))
        ->assertOk()
        ->assertSee('css/portal-v2.css')
        ->assertSee('data-result="authentic"', false)
        ->assertSee('Authentic Barangay Document')
        ->assertSee('data-portal-language-form', false)
        ->assertSeeInOrder(['Verified Resident Name', 'CERT-AUTHENTIC-TEST', 'AUTHENTICTESTCODE']);
});

test('staff can preview a request before issuing it without anything being issued, printed or verifiable', function () {
    Storage::fake('local');
    Storage::fake('public');
    $this->travelTo('2026-10-03 09:00:00');
    $resident = Resident::factory()->create(['first_name' => 'Ana', 'middle_name' => null, 'last_name' => 'Reyes', 'date_of_birth' => '1990-02-12', 'civil_status' => 'Single', 'address' => '151 Ragatan']);
    $request = DocumentRequest::query()->create([
        'reference_code' => 'REQ-PREVIEW-1', 'resident_id' => $resident->id, 'document_type' => CertificateType::CertificateOfResidency->value,
        'full_name' => $resident->full_name, 'address' => $resident->address, 'purpose' => 'Senior citizen', 'status' => 'ready_for_release',
    ]);
    $staff = User::factory()->assignedOperations()->create();

    $this->actingAs($staff)->get(route('staff.document-requests.certificate-preview', $request))
        ->assertUnprocessable()->assertSeeText('This certificate cannot be issued yet')->assertSeeText('Missing: Resident since (month and year)');

    $preview = $this->get(route('staff.document-requests.certificate-preview', [$request, 'residing_since' => '2001-07']))
        ->assertOk()
        ->assertSeeText('This is to certify that Mr./ Mrs. Ana Reyes, 36 years old, Single, a bona fide resident of 151 Ragatan Barangay Anabu I-G, City of Imus Cavite since July 2001.')
        ->assertSee('class="sheet preview residency"', false)->assertSee('data-printable="false"', false)
        ->assertSeeText('Preview only: this certificate has not been issued and cannot be printed from here.')
        ->assertDontSee('Certificate authenticity QR code')->assertDontSee('Print certificate</button>', false);
    expect($preview->getContent())->not->toContain('qrcodes/')
        ->and($preview->headers->get('Cache-Control'))->toContain('no-store');

    $this->assertDatabaseEmpty('issued_certificates');
    expect($request->fresh()->status)->toBe('ready_for_release');
    Storage::disk('public')->assertDirectoryEmpty('qrcodes');
});

test('a preview lists eligibility problems and is limited to staff who may issue certificates', function () {
    $resident = Resident::factory()->create(['is_in_good_standing' => true, 'is_verified_indigent' => false]);
    $request = DocumentRequest::query()->create([
        'reference_code' => 'REQ-PREVIEW-2', 'resident_id' => $resident->id, 'document_type' => CertificateType::CertificateOfIndigency->value,
        'full_name' => $resident->full_name, 'address' => $resident->address, 'purpose' => 'Medical assistance', 'status' => 'ready_for_release',
    ]);

    $this->actingAs(User::factory()->assignedOperations()->create())->get(route('staff.document-requests.certificate-preview', $request))
        ->assertUnprocessable()->assertSeeText('Indigency must be verified by authorized barangay staff.')->assertDontSeeText('INDIGENT family');
    $this->actingAs(User::factory()->assignedOperations()->create(['role' => 'viewer']))
        ->get(route('staff.document-requests.certificate-preview', $request))->assertForbidden();
    $this->actingAs(User::factory()->resident()->create(['resident_id' => $resident->id]), 'web')
        ->get(route('staff.document-requests.certificate-preview', $request))->assertForbidden();
});

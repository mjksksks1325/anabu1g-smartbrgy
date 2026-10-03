<?php

use App\Actions\IssueCertificate;
use App\CertificateType;
use App\Models\DocumentRequest;
use App\Models\IssuedCertificate;
use App\Models\Resident;
use App\Models\User;
use App\Models\VoterRegistration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
    $staff = User::factory()->create();
    $this->actingAs($staff, 'web')->patchJson(route('admin.document-requests.update-status', $request), ['status' => 'processing'])->assertOk();
    $this->patchJson(route('admin.document-requests.update-status', $request), ['status' => 'ready_for_release'])->assertOk();
    $this->actingAs($staff, 'web')->postJson(route('admin.document-requests.issue', $request), ['expires_on' => '2026-12-31'])
        ->assertOk()->assertJsonPath('certificate.certificate_type', $type->value);
    $certificate = IssuedCertificate::query()->sole();
    expect($request->fresh()->status)->toBe('released');
    Storage::disk('local')->assertExists($certificate->photo_path);
    Storage::disk('public')->assertExists(str_replace('/storage/', '', $certificate->qr_code_path));
    expect($certificate->photo_path)->not->toBe($resident->photo_path);
    $originalPhoto = Storage::disk('local')->get($certificate->photo_path);
    $resident->update(['first_name' => 'Changed', 'address' => 'Changed address', 'nationality' => null, 'date_of_birth' => '1980-01-01', 'gender' => 'Male', 'civil_status' => 'Married']);
    $this->postJson(route('admin.residents.photo.store', $resident), ['photo' => UploadedFile::fake()->image('replacement.png')])->assertOk();
    expect(Storage::disk('local')->get($certificate->photo_path))->toBe($originalPhoto);
    $print = $this->get(route('admin.issued-certificates.print', $certificate))->assertOk();
    foreach (['Province of Cavite', 'OFFICE OF THE SANGGUNIANG BARANGAY', 'Test Resident', 'Example Street, Anabu I-G', 'February 12, 1990', 'Female', 'Single', 'Test nationality', 'Test assistance', 'October 03, 2026', 'December 31, 2026', 'EXPIRATION DATE', 'Resident photo at issuance', 'registered voter'] as $text) {
        $print->assertSee($text);
    }
    $print->assertDontSee('Changed address')->assertSee(route('admin.issued-certificates.photo', $certificate));
    $print->assertDontSeeText('Certificate No.:')->assertDontSeeText('Verification code:')
        ->assertDontSeeText($certificate->certificate_number)->assertDontSeeText($certificate->verification_code)
        ->assertSee(asset($certificate->qr_code_path));
    $print->assertSee(asset('images/certificates/anabu-source-seal.png'))
        ->assertSeeInOrder(['Resident photo at issuance', 'To Whom it may concern', 'FULL:', 'PURPOSE:', 'DATE ISSUE:', 'EXPIRATION DATE:', 'Issued this', 'Scan QR code to verify', 'City of Imus footer logo']);
    if ($type === CertificateType::RegisteredVoterCertification) {
        $print->assertSeeInOrder(['Right Thumb Mark', 'Signature over Printed Name', 'Scan QR code to verify']);
    } else {
        $print->assertSee('INDIGENT family')->assertDontSee('Right Thumb Mark');
    }
    $this->get(route('admin.issued-certificates.photo', $certificate))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->postJson(route('admin.document-requests.issue', $request))->assertConflict();
    $verification = $this->getJson(route('certificate.verify', $certificate->verification_code))->assertOk();
    expect(array_keys($verification->json('certificate')))->toBe(['certificate_number', 'verification_code', 'certificate_type', 'resident_name', 'purpose', 'issued_at', 'issued_by']);
    $this->getJson(route('certificate.verify', 'INVALID-CODE'))->assertNotFound();
    $other = User::factory()->resident()->create();
    $this->actingAs($other, 'web')->get(route('admin.issued-certificates.print', $certificate))->assertForbidden();
    $this->get(route('admin.issued-certificates.photo', $certificate))->assertForbidden();
    $this->actingAs($other, 'resident')->getJson(route('portal.request.status', $request->reference_code))->assertNotFound();
})->with([CertificateType::RegisteredVoterCertification, CertificateType::CertificateOfIndigency]);

test('official certificates allow missing legacy photos and nationality without inventing expiry', function () {
    Storage::fake('local');
    Storage::fake('public');
    $resident = Resident::factory()->create(['is_in_good_standing' => true]);
    VoterRegistration::factory()->create(['resident_id' => $resident->id]);
    $staff = User::factory()->create();
    $this->actingAs($staff)->postJson(route('admin.issued-certificates.store'), [
        'certificate_type' => CertificateType::RegisteredVoterCertification->value,
        'resident_id' => $resident->id, 'resident_name' => $resident->full_name, 'address' => $resident->address,
    ])->assertOk();
    $certificate = IssuedCertificate::query()->sole();
    expect($certificate->expires_on)->toBeNull()->and($certificate->resident_snapshot['nationality'])->toBeNull();
    $this->get(route('admin.issued-certificates.print', $certificate))->assertOk()->assertSee('Resident photo not provided')->assertDontSee('Filipino');
    $this->get(route('admin.issued-certificates.photo', $certificate))->assertNotFound();
});

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
    $this->actingAs(User::factory()->create())->postJson(route('admin.issued-certificates.store'), [
        'certificate_type' => CertificateType::CertificateOfIndigency->value,
        'resident_id' => $condition === 'unlinked' ? null : $resident->id,
        'resident_name' => $resident->full_name, 'address' => $resident->address,
    ])->assertUnprocessable();
    $this->assertDatabaseEmpty('issued_certificates');
    $this->assertDatabaseEmpty('document_requests');
})->with(['not indigent', 'inactive voter', 'tampered voter', 'missing voter', 'not good standing', 'inactive resident', 'unlinked']);

test('staff supplied expiration dates reject malformed and past dates', function (string $expiry) {
    $this->actingAs(User::factory()->create())->postJson(route('admin.issued-certificates.store'), [
        'certificate_type' => CertificateType::BarangayClearance->value,
        'resident_name' => 'Test Resident', 'address' => 'Test address', 'expires_on' => $expiry,
    ])->assertUnprocessable()->assertJsonValidationErrors('expires_on');
    $this->assertDatabaseEmpty('issued_certificates');
})->with(['2020-01-01', 'invalid', '2026-02-31']);

test('registered voter certificates reject unverified voter records before issuing', function (string $condition) {
    Storage::fake('public');
    $resident = Resident::factory()->create(['is_in_good_standing' => true]);
    if ($condition !== 'missing') {
        VoterRegistration::factory()->create(['resident_id' => $resident->id, 'status' => 'deregistered']);
    }
    $this->actingAs(User::factory()->create())->postJson(route('admin.issued-certificates.store'), [
        'certificate_type' => CertificateType::RegisteredVoterCertification->value,
        'resident_id' => $resident->id, 'resident_name' => $resident->full_name, 'address' => $resident->address,
    ])->assertUnprocessable();
    $this->assertDatabaseEmpty('issued_certificates');
})->with(['missing', 'deregistered']);

test('failed persistence cleans up certificate photo and QR snapshots without losing the resident photo', function () {
    Storage::fake('local');
    Storage::fake('public');
    $resident = Resident::factory()->create();
    $photo = UploadedFile::fake()->image('resident.jpg')->store('resident-photos', 'local');
    $resident->update(['photo_path' => $photo]);
    IssuedCertificate::creating(function (): void {
        throw new RuntimeException('Simulated certificate persistence failure.');
    });
    expect(fn () => app(IssueCertificate::class)->handle([
        'certificate_type' => CertificateType::CertificateOfResidency->value,
        'resident_id' => $resident->id, 'resident_name' => $resident->full_name, 'address' => $resident->address,
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
        'resident_name' => $input, 'purpose' => $input, 'issued_at' => now(),
        'resident_snapshot' => ['address' => $input, 'nationality' => $input],
    ]);
    $this->actingAs(User::factory()->create())->get(route('admin.issued-certificates.print', $certificate))
        ->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee($input, false);
});

test('every paper certificate uses the shared historical fields and its own wording', function (CertificateType $type, string $wording) {
    $certificate = IssuedCertificate::query()->create([
        'certificate_number' => 'CERT-SHARED-TEST', 'verification_code' => 'SHAREDPRINTVERIFY',
        'certificate_type' => $type->value, 'resident_name' => 'Test Resident', 'purpose' => 'Test purpose',
        'issued_at' => '2026-10-03 09:00:00', 'expires_on' => '2026-12-31',
        'qr_code_path' => '/storage/qrcodes/shared-test.svg',
        'resident_snapshot' => ['address' => 'Historical test address', 'date_of_birth' => '1990-02-12', 'gender' => 'Female', 'civil_status' => 'Single', 'nationality' => 'Test nationality'],
    ]);
    $this->actingAs(User::factory()->create())->get(route('admin.issued-certificates.print', $certificate))
        ->assertOk()->assertSeeText($wording)->assertSeeText('Historical test address')->assertSeeText('February 12, 1990')
        ->assertSeeText('Female')->assertSeeText('Single')->assertSeeText('Test nationality')->assertSeeText('Test purpose')
        ->assertSeeText('December 31, 2026')->assertSee('Resident photo not provided')
        ->assertSee('Certificate authenticity QR code')->assertSee('City of Imus footer logo')
        ->assertDontSeeText('registered voter')->assertDontSeeText('INDIGENT family')
        ->assertDontSeeText('Right Thumb Mark')->assertDontSeeText('Certificate No.:')->assertDontSeeText('SHAREDPRINTVERIFY');
})->with([
    [CertificateType::BarangayClearance, 'based on the records available'],
    [CertificateType::CertificateOfResidency, 'is a bona fide resident'],
    [CertificateType::FirstTimeJobseeker, "resident's application as a first-time jobseeker"],
    [CertificateType::BusinessClearance, 'has been issued this Barangay Business Clearance'],
]);

test('legacy indigency prints the shared design without retroactively asserting verified indigency', function () {
    $certificate = IssuedCertificate::query()->create([
        'certificate_number' => 'CERT-LEGACY-TEST', 'verification_code' => 'LEGACYPRINTVERIFY',
        'certificate_type' => CertificateType::CertificateOfIndigency->value,
        'resident_name' => 'Historical Resident', 'purpose' => 'Historical purpose', 'issued_at' => now(),
    ]);
    $this->actingAs(User::factory()->create())->get(route('admin.issued-certificates.print', $certificate))
        ->assertOk()->assertSeeText('requesting this Certificate of Indigency')
        ->assertSeeText('Historical Resident')->assertSeeText('Historical purpose')->assertSee('City of Imus footer logo')
        ->assertSee('Resident photo not provided')->assertDontSeeText('INDIGENT family')->assertDontSeeText('registered voter')
        ->assertDontSeeText('Filipino')->assertDontSeeText('Certificate No.:')->assertDontSeeText('LEGACYPRINTVERIFY');
});

test('the ID card reuses its private photo while keeping its identification fields', function () {
    $certificate = IssuedCertificate::query()->create([
        'certificate_number' => 'CERT-ID-TEST', 'verification_code' => 'IDPRINTVERIFY',
        'certificate_type' => CertificateType::BarangayId->value,
        'resident_name' => 'Test Resident', 'issued_at' => now(), 'photo_path' => 'certificate-photos/test.jpg',
        'qr_code_path' => '/storage/qrcodes/id-test.svg',
    ]);
    $this->actingAs(User::factory()->create())->get(route('admin.issued-certificates.print', $certificate))
        ->assertOk()->assertSee('BARANGAY RESIDENT IDENTIFICATION CARD')->assertSeeText('ID No.:')->assertSeeText('CERT-ID-TEST')
        ->assertSee(route('admin.issued-certificates.photo', $certificate))->assertSee('Resident photo at issuance')
        ->assertSee('City of Imus footer logo')->assertSee('Certificate authenticity QR code')->assertDontSeeText('IDPRINTVERIFY');
});

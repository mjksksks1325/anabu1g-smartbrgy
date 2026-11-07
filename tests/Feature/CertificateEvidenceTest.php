<?php

use App\Actions\IssueAdditionalCertificate;
use App\AdditionalCertificateType;
use App\Models\CertificateEvidence;
use App\Models\IssuedCertificate;
use App\Models\Resident;
use App\Models\User;
use App\Models\VoterRegistration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    $this->travelTo('2026-11-07 09:00:00');
});

function probationResident(): Resident
{
    $resident = Resident::factory()->create([
        'first_name' => 'Carlo', 'middle_name' => 'Ramos', 'last_name' => 'Villanueva', 'date_of_birth' => '1976-01-19',
        'gender' => 'Male', 'civil_status' => 'Married', 'nationality' => 'Filipino', 'status' => 'active', 'is_in_good_standing' => true,
        'photo_path' => UploadedFile::fake()->image('resident.jpg')->store('resident-photos', 'local'),
    ]);
    VoterRegistration::factory()->create(['resident_id' => $resident->id, 'status' => 'active']);

    return $resident;
}

function probationIssuer(): User
{
    return User::factory()->create(['role' => 'staff', 'name' => 'Records Officer', 'staff_permissions' => ['documents.view', 'documents.issue', 'documents.print']]);
}

test('staff upload community service photographs privately, re-encoded as new JPEG files under random names', function () {
    $resident = probationResident();
    $staff = probationIssuer();
    $withPayload = UploadedFile::fake()->createWithContent('site.jpg', UploadedFile::fake()->image('site.jpg', 900, 1200)->getContent().'<?php echo "payload"; ?>');

    $this->actingAs($staff)->post(route('staff.additional-certificates.evidence.store', ['probation', $resident]), [
        'photos' => [$withPayload, UploadedFile::fake()->image('cleanup.png', 1600, 900)],
    ])->assertRedirect(route('staff.additional-certificates.create', ['probation', 'resident_id' => $resident->id]))
        ->assertSessionHas('status', '2 photographs added.');

    $evidence = CertificateEvidence::query()->orderBy('id')->get();
    expect($evidence)->toHaveCount(2)
        ->and($evidence->pluck('uploaded_by')->unique()->all())->toBe([$staff->id])
        ->and($evidence->pluck('mime_type')->unique()->all())->toBe(['image/jpeg'])
        ->and($evidence[1]->only(['width', 'height']))->toBe(['width' => 1600, 'height' => 900]);
    foreach ($evidence as $photograph) {
        expect($photograph->path)->toBe('certificate-evidence/'.$photograph->uuid.'.jpg')->not->toContain('site')->not->toContain('cleanup');
        $stored = Storage::disk('local')->get($photograph->path);
        expect(str_starts_with($stored, "\xFF\xD8"))->toBeTrue()
            ->and($stored)->not->toContain('payload')
            ->and(hash('sha256', $stored))->toBe($photograph->sha256);
    }
    expect(Storage::disk('public')->allFiles())->toBe([]);
    $this->assertDatabaseHas('administrative_audits', ['user_id' => $staff->id, 'action' => 'admin.additional-certificates.evidence.store', 'record' => 'probation, '.$resident->id]);
});

test('uploads that are not real photographs, too small, too large or too many are refused and nothing is stored', function (Closure $files, string $error) {
    $resident = probationResident();
    CertificateEvidence::factory()->count(3)->create(['resident_id' => $resident->id]);

    $this->actingAs(probationIssuer())->post(route('staff.additional-certificates.evidence.store', ['probation', $resident]), ['photos' => $files()])
        ->assertSessionHasErrors($error);

    expect(CertificateEvidence::query()->count())->toBe(3)
        ->and(Storage::disk('local')->files('certificate-evidence'))->toHaveCount(3);
})->with([
    'script renamed to .jpg' => [fn () => [UploadedFile::fake()->createWithContent('evidence.jpg', '<?php system($_GET["c"]); ?>')], 'photos.0'],
    'svg drawing' => [fn () => [UploadedFile::fake()->createWithContent('evidence.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')], 'photos.0'],
    'pdf document' => [fn () => [UploadedFile::fake()->create('evidence.pdf', 100, 'application/pdf')], 'photos.0'],
    'too small' => [fn () => [UploadedFile::fake()->image('tiny.jpg', 120, 120)], 'photos.0'],
    'too large' => [fn () => [UploadedFile::fake()->image('huge.jpg', 900, 900)->size(9000)], 'photos.0'],
    'more than the certificate holds' => [fn () => [UploadedFile::fake()->image('a.jpg', 800, 800), UploadedFile::fake()->image('b.jpg', 800, 800)], 'photos'],
    'no file' => [fn () => [], 'photos'],
]);

test('evidence photographs are served only to authorized staff, by an unguessable identifier', function () {
    $draft = CertificateEvidence::factory()->create();
    $issued = CertificateEvidence::factory()->create(['issued_certificate_id' => IssuedCertificate::query()->create(['certificate_number' => 'CERT-EVID-1', 'verification_code' => 'EVIDVERIFY000001', 'certificate_type' => AdditionalCertificateType::Probation->value, 'resident_name' => 'Carlo', 'issued_at' => now()])->id]);

    $this->actingAs(probationIssuer())->get(route('staff.certificate-evidence.show', $draft))
        ->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('X-Content-Type-Options', 'nosniff');
    $printOnly = User::factory()->create(['role' => 'staff', 'staff_permissions' => ['documents.view', 'documents.print']]);
    $this->actingAs($printOnly)->get(route('staff.certificate-evidence.show', $draft))->assertForbidden();
    $this->actingAs($printOnly)->get(route('staff.certificate-evidence.show', $issued))->assertOk();
    $this->actingAs(User::factory()->assignedOperations()->create(['role' => 'viewer']))->get(route('staff.certificate-evidence.show', $issued))->assertForbidden();
    $this->actingAs(User::factory()->resident()->create(['resident_id' => $draft->resident_id]), 'web')->get(route('staff.certificate-evidence.show', $draft))->assertForbidden();
    $this->actingAs(probationIssuer())->get('/staff/certificate-evidence/'.$draft->id)->assertNotFound();
    $this->get('/staff/certificate-evidence/..%2F..%2F.env')->assertNotFound();
    expect(route('staff.certificate-evidence.show', $draft))->toContain($draft->uuid)->not->toContain('certificate-evidence/'.$draft->uuid.'.jpg');
});

test('staff remove draft photographs, but photographs of an issued certificate stay with it', function () {
    $draft = CertificateEvidence::factory()->create();
    $issued = CertificateEvidence::factory()->create(['issued_certificate_id' => IssuedCertificate::query()->create(['certificate_number' => 'CERT-EVID-2', 'verification_code' => 'EVIDVERIFY000002', 'certificate_type' => AdditionalCertificateType::Probation->value, 'resident_name' => 'Carlo', 'issued_at' => now()])->id]);
    $staff = probationIssuer();

    $this->actingAs(User::factory()->create(['role' => 'staff', 'staff_permissions' => ['documents.view', 'documents.print']]))
        ->delete(route('staff.certificate-evidence.destroy', $draft))->assertForbidden();
    $this->actingAs($staff)->delete(route('staff.certificate-evidence.destroy', $draft))->assertRedirect()->assertSessionHas('status', 'Photograph removed.');
    expect($draft->fresh())->toBeNull();
    Storage::disk('local')->assertMissing($draft->path);
    $this->assertDatabaseHas('administrative_audits', ['user_id' => $staff->id, 'action' => 'admin.certificate-evidence.destroy']);

    $this->delete(route('staff.certificate-evidence.destroy', $issued))->assertSessionHasErrors(['photos' => 'This photograph belongs to an issued certificate and cannot be removed.']);
    expect($issued->fresh())->not->toBeNull();
    Storage::disk('local')->assertExists($issued->path);
});

test('the probation certificate needs at least one photograph and previews as a two-page document', function () {
    $resident = probationResident();
    $form = ['resident_id' => $resident->id, 'details' => ['service_completed_on' => '2026-11-03', 'purpose' => 'Probation']];
    $staff = probationIssuer();

    $this->actingAs($staff)->from(route('staff.additional-certificates.create', 'probation'))->post(route('staff.additional-certificates.preview', 'probation'), $form)
        ->assertSessionHasErrors(['certificate' => 'Upload at least one community service photograph in step 3.']);

    $photographs = CertificateEvidence::factory()->count(2)->create(['resident_id' => $resident->id]);
    CertificateEvidence::factory()->create();
    $preview = $this->post(route('staff.additional-certificates.preview', 'probation'), $form)->assertOk();
    expect(substr_count($preview->getContent(), '<section class="sheet preview'))->toBe(2)
        ->and(substr_count($preview->getContent(), 'Community service photograph '))->toBe(2);
    $preview->assertSee('data-photo-count="2"', false)
        ->assertSee(route('staff.certificate-evidence.show', $photographs[0]), false)
        ->assertDontSee('Certificate authenticity QR code');
    $this->assertDatabaseEmpty('issued_certificates');
    expect(CertificateEvidence::query()->whereNotNull('issued_certificate_id')->count())->toBe(0);

    $this->actingAs($staff)->get(route('staff.additional-certificates.create', ['probation', 'resident_id' => $resident->id]))
        ->assertOk()->assertSee('data-evidence-panel', false)->assertSeeText('2 of 4 photographs')
        ->assertSee(route('staff.certificate-evidence.destroy', $photographs[1]), false);
});

test('uploading evidence does not issue the probation certificate, and issuing it once approved keeps its photographs', function () {
    $resident = probationResident();
    $photographs = CertificateEvidence::factory()->count(3)->create(['resident_id' => $resident->id]);
    $form = ['resident_id' => $resident->id, 'details' => ['service_completed_on' => '2026-11-03', 'purpose' => 'Probation']];
    $staff = probationIssuer();

    $this->actingAs($staff)->post(route('staff.additional-certificates.store', 'probation'), $form)->assertSessionHasErrors('certificate');
    $this->assertDatabaseEmpty('issued_certificates');

    config(['certificates.additional.probation' => ['fee' => 0, 'enabled' => true]]);
    $this->post(route('staff.additional-certificates.store', 'probation'), $form)->assertRedirect();
    $certificate = IssuedCertificate::query()->sole();

    expect(CertificateEvidence::query()->where('issued_certificate_id', $certificate->id)->pluck('uuid')->all())->toBe($photographs->pluck('uuid')->all())
        ->and($certificate->resident_snapshot['evidence'])->toBe($photographs->pluck('uuid')->all());
    $print = $this->get(route('staff.issued-certificates.print', $certificate))->assertOk();
    expect(substr_count($print->getContent(), '<section class="sheet '))->toBe(2)
        ->and(substr_count($print->getContent(), 'Certificate authenticity QR code'))->toBe(1);
    $print->assertSee('data-photo-count="3"', false);
    $this->get(route('staff.additional-certificates.create', ['probation', 'resident_id' => $resident->id]))->assertSeeText('0 of 4 photographs');
    $this->getJson(route('certificate.verify', $certificate->verification_code))->assertOk()
        ->assertJsonMissingPath('certificate.evidence')->assertDontSee('certificate-evidence');
});

test('old drafts are purged and expired certificates take their photographs with them', function () {
    $old = CertificateEvidence::factory()->create(['created_at' => now()->subDays(20)]);
    $recent = CertificateEvidence::factory()->create(['created_at' => now()->subDays(2)]);
    $this->artisan('certificates:purge-draft-evidence')->assertSuccessful();
    expect($old->fresh())->toBeNull()->and($recent->fresh())->not->toBeNull();
    Storage::disk('local')->assertMissing($old->path);

    $certificate = IssuedCertificate::query()->create(['certificate_number' => 'CERT-EVID-3', 'verification_code' => 'EVIDVERIFY000003', 'certificate_type' => AdditionalCertificateType::Probation->value, 'resident_name' => 'Carlo', 'issued_at' => now()->subYear(), 'expires_on' => now()->subMonths(9)->toDateString(), 'template_version' => '2026.11']);
    $linked = CertificateEvidence::factory()->create(['issued_certificate_id' => $certificate->id, 'created_at' => now()->subYear()]);
    $this->artisan('certificates:purge-expired')->assertSuccessful();
    expect($certificate->fresh())->toBeNull()->and($linked->fresh())->toBeNull()->and($recent->fresh())->not->toBeNull();
    Storage::disk('local')->assertMissing($linked->path);
});

test('evidence uploads are limited to certificates that use them and to staff who may issue', function () {
    $resident = probationResident();
    $photo = fn () => ['photos' => [UploadedFile::fake()->image('a.jpg', 800, 800)]];

    $this->actingAs(probationIssuer())->post(route('staff.additional-certificates.evidence.store', ['low-income', $resident]), $photo())->assertNotFound();
    $this->actingAs(User::factory()->create(['role' => 'staff', 'staff_permissions' => ['documents.view', 'documents.print']]))
        ->post(route('staff.additional-certificates.evidence.store', ['probation', $resident]), $photo())->assertForbidden();
    expect(CertificateEvidence::query()->count())->toBe(0)->and(app(IssueAdditionalCertificate::class))->toBeInstanceOf(IssueAdditionalCertificate::class);
});

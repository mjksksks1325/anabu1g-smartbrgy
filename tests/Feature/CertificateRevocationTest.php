<?php

use App\CertificateDocument;
use App\CertificateType;
use App\Models\IssuedCertificate;
use App\Models\User;
use App\StaffPermissions;
use Illuminate\Support\Facades\Storage;

/**
 * @param  array<string, mixed>  $attributes  May include the revocation columns, which are not mass assignable.
 */
function revocableCertificate(array $attributes = []): IssuedCertificate
{
    return IssuedCertificate::query()->forceCreate([
        'certificate_number' => 'CERT-2026-REVOKE01', 'verification_code' => 'REVOKEVERIFYCODE',
        'certificate_type' => CertificateType::BarangayClearance->value, 'template_version' => CertificateDocument::TEMPLATE_VERSION,
        'resident_name' => 'Maria Santos Dela Cruz', 'purpose' => 'Confidential purpose', 'issued_at' => now(), 'issued_by' => 'Records Officer',
        'resident_snapshot' => ['full_name' => 'Maria Santos Dela Cruz', 'address' => 'Anabu I-G'],
        ...$attributes,
    ]);
}

test('authorized staff revoke a certificate with a reason and the supervisor password; the record and its history stay', function () {
    $this->travelTo('2026-11-07 10:00:00');
    $certificate = revocableCertificate();
    $officer = User::factory()->create(['role' => 'staff', 'name' => 'Revoking Officer', 'staff_permissions' => ['documents.view', 'documents.print', 'documents.revoke']]);

    $this->actingAs($officer)->postJson(route('staff.issued-certificates.revoke', $certificate), ['reason' => 'Issued to the wrong person by mistake.', ...supervisorApproval()])
        ->assertOk()->assertJsonPath('certificate.certificate_number', 'CERT-2026-REVOKE01');

    $certificate->refresh();
    expect($certificate->revoked_at?->toDateTimeString())->toBe('2026-11-07 10:00:00')
        ->and($certificate->revoked_by)->toBe($officer->id)
        ->and($certificate->revocation_reason)->toBe('Issued to the wrong person by mistake.')
        ->and($certificate->resident_name)->toBe('Maria Santos Dela Cruz')
        ->and($certificate->issued_by)->toBe('Records Officer');
    $this->assertDatabaseHas('administrative_audits', ['user_id' => $officer->id, 'action' => 'admin.issued-certificates.revoke', 'type' => 'cert', 'record' => (string) $certificate->id]);

    $this->get(route('staff.issued-certificates.print', $certificate))
        ->assertStatus(409)->assertViewIs('admin.certificates.unavailable')
        ->assertSeeText('This certificate was revoked')->assertSeeText('Issued to the wrong person by mistake.')->assertSeeText('Revoking Officer')
        ->assertDontSee('Print certificate');
    $this->getJson(route('staff.issued-certificates.index'))->assertJsonPath('data.0.revoked_at', $certificate->revoked_at->toIso8601String());

    $this->postJson(route('staff.issued-certificates.revoke', $certificate), ['reason' => 'Trying to revoke it a second time.', ...supervisorApproval()])
        ->assertConflict()->assertJsonPath('message', 'This certificate was already revoked.');
    $this->assertDatabaseCount('issued_certificates', 1);
});

test('revoked certificates verify as revoked without exposing the holder, purpose, reason or staff', function () {
    $certificate = revocableCertificate(['revoked_at' => '2026-11-07 10:00:00', 'revocation_reason' => 'Confidential revocation reason']);

    $this->getJson(route('certificate.verify', $certificate->verification_code))
        ->assertStatus(410)->assertJsonPath('status', 'revoked')
        ->assertExactJson(['success' => false, 'status' => 'revoked', 'message' => 'This certificate has been revoked by Barangay Anabu I-G.', 'certificate' => ['certificate_number' => 'CERT-2026-REVOKE01', 'certificate_type' => 'Barangay Clearance', 'revoked_on' => '2026-11-07']]);
    $this->get(route('certificate.verify', $certificate->verification_code))
        ->assertStatus(410)->assertSee('data-result="revoked"', false)->assertSeeText('Revoked Barangay Document')->assertSeeText('CERT-2026-REVOKE01')
        ->assertDontSee('Maria Santos')->assertDontSee('Confidential purpose')->assertDontSee('Confidential revocation reason')->assertDontSee('Records Officer')
        ->assertDontSee('Authentic Barangay Document');
});

test('revoking needs the revoke permission, a reason and the correct supervisor password', function (?Closure $actor, array $payload, int $status) {
    $certificate = revocableCertificate();
    User::factory()->create(['role' => 'captain', 'is_active' => true]);
    if ($actor !== null) {
        $this->actingAs($actor());
    }

    $this->postJson(route('staff.issued-certificates.revoke', $certificate), $payload)->assertStatus($status);
    expect($certificate->fresh()->revoked_at)->toBeNull();
    $this->assertDatabaseMissing('administrative_audits', ['action' => 'admin.issued-certificates.revoke']);
})->with([
    'guest' => [null, ['reason' => 'A valid reason here.', 'super_admin_password' => 'password'], 401],
    'documents officer without revoke' => [fn () => User::factory()->create(['role' => 'staff', 'staff_permissions' => StaffPermissions::presets()['Documents Officer']]), ['reason' => 'A valid reason here.', 'super_admin_password' => 'password'], 403],
    'secretary preset' => [fn () => User::factory()->create(['role' => 'staff', 'staff_permissions' => StaffPermissions::presets()['Secretary / Assistant']]), ['reason' => 'A valid reason here.', 'super_admin_password' => 'password'], 403],
    'view-only account' => [fn () => User::factory()->assignedOperations()->create(['role' => 'viewer']), ['reason' => 'A valid reason here.', 'super_admin_password' => 'password'], 403],
    'resident account' => [fn () => User::factory()->resident()->create(), ['reason' => 'A valid reason here.', 'super_admin_password' => 'password'], 403],
    'no supervisor password' => [fn () => User::factory()->create(['role' => 'staff', 'staff_permissions' => ['documents.revoke']]), ['reason' => 'A valid reason here.'], 422],
    'wrong supervisor password' => [fn () => User::factory()->create(['role' => 'staff', 'staff_permissions' => ['documents.revoke']]), ['reason' => 'A valid reason here.', 'super_admin_password' => 'wrong-password'], 422],
    'reason too short' => [fn () => User::factory()->create(['role' => 'staff', 'staff_permissions' => ['documents.revoke']]), ['reason' => 'Mistake', 'super_admin_password' => 'password'], 422],
]);

test('only administrators hold the revoke permission by default; staff get it only when assigned', function () {
    expect(User::factory()->superAdmin()->create()->hasPermission('documents.revoke'))->toBeTrue()
        ->and(User::factory()->create(['role' => 'captain'])->hasPermission('documents.revoke'))->toBeTrue()
        ->and(User::factory()->create(['role' => 'staff', 'staff_permissions' => StaffPermissions::presets()['Documents Officer']])->hasPermission('documents.revoke'))->toBeFalse()
        ->and(StaffPermissions::presets()['Secretary / Assistant'])->not->toContain('documents.revoke')
        ->and(collect(StaffPermissions::presets())->flatten()->contains('documents.revoke'))->toBeFalse()
        ->and(User::factory()->create(['role' => 'staff', 'staff_permissions' => ['documents.revoke']])->hasPermission('documents.revoke'))->toBeTrue()
        ->and(StaffPermissions::forRoute('staff.issued-certificates.revoke'))->toBe(['documents.revoke']);
});

test('valid certificates verify publicly with only the minimum details', function () {
    $this->travelTo('2026-11-07 10:00:00');
    $certificate = revocableCertificate();

    $this->getJson(route('certificate.verify', $certificate->verification_code))->assertOk()
        ->assertExactJson(['success' => true, 'status' => 'valid', 'certificate' => [
            'certificate_number' => 'CERT-2026-REVOKE01', 'certificate_type' => 'Barangay Clearance', 'holder_initials' => 'M. S. D. C.',
            'issued_on' => '2026-11-07', 'expires_on' => '2027-02-07',
        ]]);
    $this->get(route('certificate.verify', $certificate->verification_code))->assertOk()
        ->assertSeeInOrder(['Authentic Barangay Document', 'CERT-2026-REVOKE01', 'Barangay Clearance', 'M. S. D. C.', 'November 07, 2026', 'Valid', 'February 07, 2027'])
        ->assertDontSee('Maria Santos')->assertDontSee('Confidential purpose')->assertDontSee('Records Officer')->assertDontSee('REVOKEVERIFYCODE');
});

test('the expiry purge keeps revoked certificates on record', function () {
    Storage::fake('public');
    $this->travelTo('2026-01-01 09:00:00');
    $revoked = revocableCertificate(['revoked_at' => now()]);
    $expired = revocableCertificate(['certificate_number' => 'CERT-2026-EXPIRED', 'verification_code' => 'EXPIREDVERIFYCODE']);
    $this->travelTo('2027-01-01 09:00:00');

    $this->artisan('certificates:purge-expired')->assertSuccessful();

    expect(IssuedCertificate::query()->pluck('id')->all())->toBe([$revoked->id])
        ->and($expired->fresh())->toBeNull();
});

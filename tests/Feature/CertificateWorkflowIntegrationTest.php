<?php

use App\AdditionalCertificateType;
use App\CertificateType;
use App\Models\IssuedCertificate;
use App\Models\Resident;
use App\Models\ResidentRequestRestriction;
use App\Models\User;
use App\Models\VoterRegistration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * @param  array<string, mixed>  $attributes  May include the revocation columns.
 */
function historyCertificate(string $number, string $type, array $attributes = []): IssuedCertificate
{
    return IssuedCertificate::query()->forceCreate([
        'certificate_number' => $number, 'verification_code' => 'V'.$number, 'certificate_type' => $type,
        'template_version' => '2026.11', 'resident_name' => 'History Resident', 'issued_at' => '2026-11-01 09:00:00', 'expires_on' => '2027-02-01',
        ...$attributes,
    ]);
}

test('certificate history filters by every certificate type, including the staff-only ones, and by status', function () {
    $this->travelTo('2026-11-07 09:00:00');
    historyCertificate('CERT-LOW-1', AdditionalCertificateType::LowIncome->value);
    historyCertificate('CERT-CLEAR-1', CertificateType::BarangayClearance->value);
    historyCertificate('CERT-EXPIRED-1', CertificateType::BarangayClearance->value, ['issued_at' => '2026-05-01 09:00:00', 'expires_on' => '2026-08-01']);
    historyCertificate('CERT-OLD-1', CertificateType::BarangayClearance->value, ['issued_at' => '2026-06-01 09:00:00', 'expires_on' => null, 'template_version' => null]);
    historyCertificate('CERT-OLDID-1', CertificateType::BarangayId->value, ['issued_at' => '2026-06-01 09:00:00', 'expires_on' => null, 'template_version' => null]);
    historyCertificate('CERT-REVOKED-1', AdditionalCertificateType::LowIncome->value, ['revoked_at' => '2026-11-05 09:00:00', 'revocation_reason' => 'Issued in error.']);
    $this->actingAs(User::factory()->assignedOperations()->create());
    $numbers = fn (array $query): array => collect($this->getJson(route('staff.issued-certificates.index', $query))->assertOk()->json('data'))->pluck('certificate_number')->sort()->values()->all();

    expect($numbers(['certificate_type' => AdditionalCertificateType::LowIncome->value]))->toBe(['CERT-LOW-1', 'CERT-REVOKED-1'])
        ->and($numbers(['status' => 'valid']))->toBe(['CERT-CLEAR-1', 'CERT-LOW-1', 'CERT-OLDID-1'])
        ->and($numbers(['status' => 'expired']))->toBe(['CERT-EXPIRED-1', 'CERT-OLD-1'])
        ->and($numbers(['status' => 'revoked']))->toBe(['CERT-REVOKED-1'])
        ->and($numbers(['certificate_type' => AdditionalCertificateType::LowIncome->value, 'status' => 'valid']))->toBe(['CERT-LOW-1'])
        ->and($numbers(['search' => 'CERT-LOW']))->toBe(['CERT-LOW-1']);

    $statuses = collect($this->getJson(route('staff.issued-certificates.index', ['per_page' => 50]))->json('data'))->pluck('status', 'certificate_number');
    expect($statuses->all())->toEqual(['CERT-LOW-1' => 'valid', 'CERT-CLEAR-1' => 'valid', 'CERT-EXPIRED-1' => 'expired', 'CERT-OLD-1' => 'expired', 'CERT-OLDID-1' => 'valid', 'CERT-REVOKED-1' => 'revoked'])
        ->and($this->getJson(route('staff.issued-certificates.index', ['per_page' => 2]))->json('last_page'))->toBe(3);
    $this->getJson(route('staff.issued-certificates.index', ['certificate_type' => 'Made-up Certificate', 'status' => 'pending']))
        ->assertJsonValidationErrors(['certificate_type', 'status']);
});

test('the history filter and the document-hold list offer the staff-only certificates', function () {
    $page = $this->actingAs(User::factory()->superAdmin()->create())->get(route('staff.document-requests.index'))->assertOk();

    $page->assertSee('id="issued-status-filter"', false)->assertSee('<optgroup label="Staff-only certificates">', false);
    foreach (AdditionalCertificateType::cases() as $type) {
        expect(substr_count($page->getContent(), '<option value="'.$type->value.'">'))->toBeGreaterThanOrEqual(2);
    }
});

test('a hold can target one staff-only certificate, and holds on every document cover them all', function () {
    Storage::fake('local');
    $resident = Resident::factory()->create(['date_of_birth' => '1930-01-01', 'nationality' => 'Filipino', 'is_in_good_standing' => true, 'special_groups' => ['4Ps Beneficiary'],
        'photo_path' => UploadedFile::fake()->image('r.jpg')->store('resident-photos', 'local')]);
    VoterRegistration::factory()->create(['resident_id' => $resident->id]);
    $admin = User::factory()->superAdmin()->create(['role' => 'admin']);

    $this->actingAs($admin)->postJson(route('staff.request-restrictions.store'), ['resident_id' => $resident->id, 'reason_category' => 'Reviewed case decision', 'internal_reason' => 'Confidential notes', 'starts_at' => now()->subMinute()->toDateTimeString(), 'affected_document_type' => AdditionalCertificateType::Nonagenarian->value])->assertCreated();
    $this->postJson(route('staff.request-restrictions.store'), ['resident_id' => $resident->id, 'reason_category' => 'X', 'internal_reason' => 'Y', 'starts_at' => now()->toDateTimeString(), 'affected_document_type' => 'Made-up Certificate'])->assertJsonValidationErrors('affected_document_type');
    $hold = ResidentRequestRestriction::query()->sole();

    $preview = fn (string $slug, array $details) => $this->actingAs(User::factory()->assignedOperations()->create())
        ->from(route('staff.additional-certificates.create', $slug))
        ->post(route('staff.additional-certificates.preview', $slug), ['resident_id' => $resident->id, 'details' => $details]);
    $nonagenarian = ['representative_name' => null];
    $moveOut = ['destination' => 'Elsewhere', 'purpose' => '4Ps'];

    $preview('nonagenarian', $nonagenarian)->assertOk();
    $this->actingAs(User::factory()->create(['role' => 'captain']))->postJson(route('staff.request-restrictions.review', $hold))->assertOk();
    $preview('nonagenarian', $nonagenarian)->assertSessionHasErrors(['certificate' => ResidentRequestRestriction::MESSAGE]);
    $preview('move-out-4ps', $moveOut)->assertOk();

    ResidentRequestRestriction::factory()->reviewed()->create(['resident_id' => $resident->id, 'affected_document_type' => null]);
    $preview('move-out-4ps', $moveOut)->assertSessionHasErrors(['certificate' => ResidentRequestRestriction::MESSAGE]);
});

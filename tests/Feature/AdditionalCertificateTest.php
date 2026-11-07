<?php

use App\AdditionalCertificateType;
use App\CertificateDocument;
use App\CertificateType;
use App\Models\CertificateEvidence;
use App\Models\Household;
use App\Models\IssuedCertificate;
use App\Models\Resident;
use App\Models\User;
use App\Models\VoterRegistration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A synthetic resident who meets every check the staff-only certificates make: active, in good standing, verified
 * voter, Filipino, 4Ps beneficiary, with a photo on file.
 *
 * @param  array<string, mixed>  $attributes  'voter' => false leaves out the voter registration.
 */
function eligibleResident(array $attributes = []): Resident
{
    $hasVoterRegistration = $attributes['voter'] ?? true;
    unset($attributes['voter']);
    $resident = Resident::factory()->create([
        'first_name' => 'Ana', 'middle_name' => 'Cruz', 'last_name' => 'Reyes', 'suffix' => null,
        'date_of_birth' => '1980-03-15', 'gender' => 'Female', 'civil_status' => 'Married', 'nationality' => 'Filipino',
        'address' => '151 Ragatan', 'status' => 'active', 'is_in_good_standing' => true, 'special_groups' => ['4Ps Beneficiary'],
        'photo_path' => UploadedFile::fake()->image('resident.jpg')->store('resident-photos', 'local'),
        ...$attributes,
    ]);
    if ($hasVoterRegistration) {
        VoterRegistration::factory()->create(['resident_id' => $resident->id, 'status' => 'active']);
    }

    return $resident;
}

/**
 * The form a staff member submits for each certificate, and a sentence of the template wording it must print.
 *
 * @return array<string, array{0: AdditionalCertificateType, 1: array<string, mixed>, 2: array<string, mixed>, 3: string}>
 */
function additionalCertificateCases(): array
{
    return [
        'low income' => [AdditionalCertificateType::LowIncome, ['occupation' => 'Labandera', 'monthly_income' => '8000', 'purpose' => 'Medical assistance'], [], 'belongs to the low-income earner family in our Barangay as a Labandera with an estimated monthly income of P 8,000.00.'],
        'guardianship' => [AdditionalCertificateType::Guardianship, ['dependents' => "Jerald Reyes\nShaira Reyes", 'purpose' => 'Solo parent'], [], 'is the guardian/caregiver of JERALD REYES and SHAIRA REYES, who is currently residing at the same address in Barangay Anabu 1-G, City of Imus Cavite.'],
        'cohabitation' => [AdditionalCertificateType::Cohabitation, ['years_together' => '10', 'purpose' => '4Ps requirement'], [], 'depose and state that they have been living together as husband and wife under the same roof, continuously and without any interruption, a period of more than Ten (10) years.'],
        'fact of birth' => [AdditionalCertificateType::FactOfBirth, ['father_name' => 'Pedro Reyes', 'mother_name' => 'Ana Cruz Reyes', 'child_name' => 'Almira Reyes', 'child_birth_date' => '2005-10-05', 'child_birth_place' => 'City of Bacoor, Cavite', 'purpose' => 'Late birth registration'], [], 'has given birth to his/her child without having them/his registered.'],
        'probation' => [AdditionalCertificateType::Probation, ['service_completed_on' => '2026-10-14', 'purpose' => 'Probation'], [], 'is a bona fide resident and registered voter of this barangay, completed her / his community service as of October 14, 2026.'],
        'national ID registration' => [AdditionalCertificateType::NationalIdRegistration, ['birth_place' => 'Surigao del Norte', 'mother_name' => 'Juana Cruz', 'father_name' => 'Nicolas Reyes', 'purpose' => 'National ID registration'], [], 'This Barangay Certification is issued upon request of the above-mentioned person for any legal purposes it may serve.'],
        '4Ps move-out' => [AdditionalCertificateType::FourPsMoveOut, ['destination' => 'Barangay Pasong Buaya II, City of Imus, Province of Cavite', 'purpose' => '4Ps requirement'], [], 'for transferring her beneficiary as a 4Ps beneficiary from Barangay Anabu I-G, City of Imus, Cavite to Barangay Pasong Buaya II, City of Imus, Province of Cavite.'],
        'no objection' => [AdditionalCertificateType::NoObjection, ['tree_count' => '4', 'tree_species' => 'mango', 'tree_location' => 'right of way'], [], 'on the following: 04 (Four) Mango Trees, which found to be located in the right of way and valid reason for cutting.'],
        'nonagenarian' => [AdditionalCertificateType::Nonagenarian, ['representative_name' => 'Jocelyn Reyes', 'representative_relationship' => 'daughter'], ['date_of_birth' => '1934-05-31'], 'I hereby authorize my daughter, Jocelyn Reyes, to claim my Nonagenarian benefit on my behalf.'],
    ];
}

/**
 * @param  array<string, mixed>  $details
 * @return array<string, mixed>
 */
function additionalCertificateForm(AdditionalCertificateType $type, Resident $resident, array $details): array
{
    $form = ['resident_id' => $resident->id, 'details' => $details];
    if ($type->acceptsEvidence()) {
        CertificateEvidence::factory()->create(['resident_id' => $resident->id, 'certificate_type' => $type->value]);
    }
    if ($type->needsPartner()) {
        $partner = eligibleResident(['first_name' => 'Pedro', 'middle_name' => null, 'last_name' => 'Santos', 'gender' => 'Male', 'household_id' => $resident->household_id]);
        $form['partner_resident_id'] = $partner->id;
    }

    return $form;
}

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    $this->travelTo('2026-11-07 09:00:00');
});

test('staff see the nine certificates with what is still to be approved, and issuing is off for every one', function () {
    $this->actingAs(User::factory()->assignedOperations()->create())->get(route('staff.additional-certificates.index'))
        ->assertOk()->assertSeeInOrder(collect(AdditionalCertificateType::cases())->map->value->all())
        ->assertSeeText('Off until approved')->assertDontSeeText('On · ')
        ->assertSeeText('Brgy. Certificate for Low-Income 2026.docx');

    foreach (AdditionalCertificateType::cases() as $type) {
        expect($type->fee())->toBeNull()->and($type->issuanceEnabled())->toBeFalse()->and($type->pendingApprovals())->toContain('Fee');
    }
});

test('each staff-only certificate previews in its official layout without being issued, and cannot be issued before approval', function (AdditionalCertificateType $type, array $details, array $residentAttributes, string $wording) {
    $resident = eligibleResident(['household_id' => Household::factory()->create()->id, ...$residentAttributes]);
    $form = additionalCertificateForm($type, $resident, $details);
    $staff = User::factory()->assignedOperations()->create();

    $this->actingAs($staff)->get(route('staff.additional-certificates.create', $type->slug()))
        ->assertOk()->assertSee('data-issuance-off', false)->assertSeeText($type->officialReference());

    $preview = $this->post(route('staff.additional-certificates.preview', $type->slug()), $form)
        ->assertOk()->assertViewIs($type->printView())
        ->assertSeeText($wording)
        ->assertSee('class="sheet preview', false)->assertSee('data-printable="false"', false)
        ->assertDontSee('Certificate authenticity QR code')->assertDontSee('Print certificate</button>', false);
    expect($preview->headers->get('Cache-Control'))->toContain('no-store');

    $this->post(route('staff.additional-certificates.store', $type->slug()), $form)
        ->assertRedirect()->assertSessionHasErrors(['certificate' => "Issuing the {$type->value} is turned off until the barangay approves its fee and requirements. You can still complete and preview it."]);
    $this->assertDatabaseEmpty('issued_certificates');
    Storage::disk('public')->assertDirectoryEmpty('qrcodes');
})->with(additionalCertificateCases());

test('once the barangay approves its fee, a staff-only certificate is issued, printed with its QR code and verifiable', function (AdditionalCertificateType $type, array $details, array $residentAttributes, string $wording) {
    config(["certificates.additional.{$type->slug()}" => ['fee' => 50, 'enabled' => true]]);
    $resident = eligibleResident(['household_id' => Household::factory()->create()->id, ...$residentAttributes]);
    $staff = User::factory()->assignedOperations()->create(['name' => 'Records Officer']);

    $response = $this->actingAs($staff)->post(route('staff.additional-certificates.store', $type->slug()), additionalCertificateForm($type, $resident, $details));
    $certificate = IssuedCertificate::query()->sole();
    $response->assertRedirect(route('staff.issued-certificates.print', $certificate));

    expect($certificate->certificate_type)->toBe($type->value)
        ->and($certificate->template_version)->toBe(CertificateDocument::TEMPLATE_VERSION)
        ->and((float) $certificate->amount_paid)->toBe(50.0)
        ->and($certificate->issued_by)->toBe('Records Officer')
        ->and($certificate->resident_snapshot['details'])->not->toBeEmpty();
    Storage::disk('public')->assertExists(str_replace('/storage/', '', $certificate->qr_code_path));

    $this->get(route('staff.issued-certificates.print', $certificate))
        ->assertOk()->assertViewIs($type->printView())->assertSeeText($wording)
        ->assertSee('Certificate authenticity QR code')->assertSee('data-printable="true"', false);
    $this->getJson(route('certificate.verify', $certificate->verification_code))->assertOk()->assertJsonPath('certificate.certificate_type', $type->value);
    $this->assertDatabaseHas('administrative_audits', ['user_id' => $staff->id, 'action' => 'admin.additional-certificates.store', 'type' => 'cert']);
})->with(additionalCertificateCases());

test('staff-only certificates refuse facts the records do not support', function (AdditionalCertificateType $type, array $residentAttributes, array $details, string $problem) {
    $resident = eligibleResident(['household_id' => Household::factory()->create()->id, ...$residentAttributes]);

    $this->actingAs(User::factory()->assignedOperations()->create())
        ->from(route('staff.additional-certificates.create', $type->slug()))
        ->post(route('staff.additional-certificates.preview', $type->slug()), additionalCertificateForm($type, $resident, $details))
        ->assertRedirect(route('staff.additional-certificates.create', $type->slug()))
        ->assertSessionHasErrors(['certificate' => $problem]);
})->with([
    'nonagenarian under 90' => [AdditionalCertificateType::Nonagenarian, ['date_of_birth' => '1940-01-01'], [], 'The resident must be at least 90 years old on the issue date.'],
    'nonagenarian not recorded as Filipino' => [AdditionalCertificateType::Nonagenarian, ['date_of_birth' => '1930-01-01', 'nationality' => 'Japanese'], [], 'The resident record must give the nationality as Filipino.'],
    'move-out without the 4Ps flag' => [AdditionalCertificateType::FourPsMoveOut, ['special_groups' => []], ['destination' => 'Somewhere', 'purpose' => '4Ps'], 'The resident record does not list the resident as a 4Ps beneficiary.'],
    'fact of birth for someone who is not a parent' => [AdditionalCertificateType::FactOfBirth, [], ['father_name' => 'Pedro Reyes', 'mother_name' => 'Someone Else', 'child_name' => 'Child', 'child_birth_date' => '2005-01-01', 'child_birth_place' => 'Imus', 'purpose' => 'Late registration'], 'The selected resident must be the father or the mother named on the certificate.'],
    'low income without a photo' => [AdditionalCertificateType::LowIncome, ['photo_path' => null], ['occupation' => 'Vendor', 'monthly_income' => '5000', 'purpose' => 'Assistance'], 'Complete these details in Resident Records first: Resident photo.'],
    'probation without a voter registration' => [AdditionalCertificateType::Probation, ['voter' => false], ['service_completed_on' => '2026-10-01', 'purpose' => 'Probation'], 'An active verified voter registration is required for the official certificate wording.'],
    'guardian under 18' => [AdditionalCertificateType::Guardianship, ['date_of_birth' => '2010-01-01'], ['dependents' => 'Child One', 'purpose' => 'Solo parent'], 'The resident must be at least 18 years old ("of legal age").'],
]);

test('a cohabitation certificate needs a partner of legal age who shares the household or address', function () {
    $resident = eligibleResident(['household_id' => Household::factory()->create()->id]);
    $elsewhere = eligibleResident(['first_name' => 'Pedro', 'gender' => 'Male', 'household_id' => Household::factory()->create()->id, 'address' => 'Another address']);

    $this->actingAs(User::factory()->assignedOperations()->create())->from(route('staff.additional-certificates.create', 'cohabitation'))
        ->post(route('staff.additional-certificates.preview', 'cohabitation'), ['resident_id' => $resident->id, 'partner_resident_id' => $elsewhere->id, 'details' => ['years_together' => 5, 'purpose' => '4Ps']])
        ->assertSessionHasErrors(['certificate' => 'The two residents do not share a household or an address on record.']);
    $this->post(route('staff.additional-certificates.preview', 'cohabitation'), ['resident_id' => $resident->id, 'partner_resident_id' => $resident->id, 'details' => ['years_together' => 5, 'purpose' => '4Ps']])
        ->assertSessionHasErrors('partner_resident_id');
});

test('staff-only certificate forms validate every required detail', function () {
    $resident = eligibleResident();
    $staff = User::factory()->assignedOperations()->create();

    $this->actingAs($staff)->post(route('staff.additional-certificates.preview', 'low-income'), ['resident_id' => $resident->id, 'details' => ['monthly_income' => '-5']])
        ->assertSessionHasErrors(['details.occupation', 'details.monthly_income', 'details.purpose']);
    $this->post(route('staff.additional-certificates.preview', 'fact-of-birth'), ['resident_id' => $resident->id, 'details' => ['child_birth_date' => '2999-01-01']])
        ->assertSessionHasErrors(['details.father_name', 'details.mother_name', 'details.child_name', 'details.child_birth_date', 'details.child_birth_place']);
    $this->post(route('staff.additional-certificates.preview', 'guardianship'), ['resident_id' => $resident->id, 'details' => ['dependents' => "  \n ", 'purpose' => 'Solo parent']])
        ->assertSessionHasErrors('details.dependents');
    $this->post(route('staff.additional-certificates.preview', 'nonagenarian'), ['resident_id' => $resident->id, 'details' => ['representative_name' => 'Someone']])
        ->assertSessionHasErrors('details.representative_relationship');
    $this->post(route('staff.additional-certificates.preview', 'probation'), ['details' => ['service_completed_on' => '2026-10-01', 'purpose' => 'x']])
        ->assertSessionHasErrors('resident_id');
    $this->get(route('staff.additional-certificates.create', 'unknown-certificate'))->assertNotFound();
});

test('only staff who may issue certificates can prepare staff-only certificates', function () {
    $resident = eligibleResident();
    $form = ['resident_id' => $resident->id, 'details' => ['tree_count' => 1, 'tree_species' => 'Mango', 'tree_location' => 'right of way']];

    foreach ([User::factory()->assignedOperations()->create(['role' => 'viewer']), User::factory()->create(['role' => 'staff', 'staff_permissions' => ['documents.view', 'documents.print']])] as $user) {
        $this->actingAs($user)->get(route('staff.additional-certificates.index'))->assertForbidden();
        $this->post(route('staff.additional-certificates.preview', 'no-objection'), $form)->assertForbidden();
        $this->post(route('staff.additional-certificates.store', 'no-objection'), $form)->assertForbidden();
    }
    $this->actingAs(User::factory()->resident()->create(['resident_id' => $resident->id]), 'web')->get(route('staff.additional-certificates.index'))->assertForbidden();
});

test('staff-only certificates are never offered or accepted in the Resident Portal', function () {
    $account = User::factory()->resident()->create(['resident_id' => eligibleResident()->id]);

    $page = $this->actingAs($account, 'resident')->get(route('portal.request.create'))->assertOk();
    foreach (AdditionalCertificateType::cases() as $type) {
        $page->assertDontSee($type->value);
        expect(CertificateType::tryFromLabel($type->value))->toBeNull();
    }
    $this->postJson(route('portal.request.store'), ['document_type' => AdditionalCertificateType::LowIncome->value, 'purpose' => 'Assistance'])
        ->assertUnprocessable()->assertJsonValidationErrors('document_type');
    $this->assertDatabaseEmpty('document_requests');
});

test('previewing is not written to the audit log, while uploads, removals and issuance still are', function () {
    $resident = eligibleResident();
    $staff = User::factory()->assignedOperations()->create();

    $this->actingAs($staff)->post(route('staff.additional-certificates.preview', 'no-objection'), ['resident_id' => $resident->id, 'details' => ['tree_count' => 2, 'tree_species' => 'Mango', 'tree_location' => 'right of way']])->assertOk();
    $this->assertDatabaseMissing('administrative_audits', ['action' => 'admin.additional-certificates.preview']);

    config(['certificates.additional.no-objection' => ['fee' => 0, 'enabled' => true]]);
    $this->post(route('staff.additional-certificates.store', 'no-objection'), ['resident_id' => $resident->id, 'details' => ['tree_count' => 2, 'tree_species' => 'Mango', 'tree_location' => 'right of way']])->assertRedirect();
    $this->assertDatabaseHas('administrative_audits', ['user_id' => $staff->id, 'action' => 'admin.additional-certificates.store']);
});

test('signature lines carry their template heights and photos appear only where the template shows one', function (AdditionalCertificateType $type, array $details, string $referenceTop, bool $printsPhoto) {
    $resident = eligibleResident(['household_id' => Household::factory()->create()->id]);

    $preview = $this->actingAs(User::factory()->assignedOperations()->create())
        ->post(route('staff.additional-certificates.preview', $type->slug()), additionalCertificateForm($type, $resident, $details))->assertOk()
        ->assertSee('class="resident-signature" data-reference-top="'.$referenceTop.'"', false);

    $printsPhoto ? $preview->assertSee('alt="Resident photo at issuance"', false) : $preview->assertDontSee('Resident photo at issuance');
})->with([
    'low income (no photo in the template)' => [AdditionalCertificateType::LowIncome, ['occupation' => 'Labandera', 'monthly_income' => '8000', 'purpose' => 'Medical assistance'], '254.9', false],
    'probation' => [AdditionalCertificateType::Probation, ['service_completed_on' => '2026-10-14', 'purpose' => 'Probation'], '251.6', true],
    'national ID registration' => [AdditionalCertificateType::NationalIdRegistration, ['birth_place' => 'Imus', 'mother_name' => 'Juana Cruz', 'father_name' => 'Nicolas Reyes', 'purpose' => 'National ID'], '255.1', true],
    '4Ps move-out' => [AdditionalCertificateType::FourPsMoveOut, ['destination' => 'Pasong Buaya II', 'purpose' => '4Ps requirement'], '240.9', true],
]);

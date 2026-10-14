<?php

use App\Models\Purok;
use App\Models\Resident;
use App\Models\ResidentRegistrationApplication;
use App\Models\User;
use App\Notifications\ResidentPortalActivationNotification;
use App\Notifications\ResidentRegistrationReviewedNotification;
use App\StaffPermissions;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

function newResidentApplicationPayload(array $overrides = []): array
{
    $purok = Purok::factory()->create(['is_active' => true]);

    return ['first_name' => 'Maria', 'middle_name' => 'Cruz', 'last_name' => 'Newresident', 'date_of_birth' => '1996-02-10',
        'gender' => 'Female', 'civil_status' => 'Single', 'residency_type' => 'Renter', 'purok' => $purok->name,
        'address' => 'Block 1, Anabu I-G', 'email' => 'newresident@example.test', 'photo' => UploadedFile::fake()->image('resident.jpg'), ...$overrides];
}

test('new application remains pending without creating a resident account or record', function () {
    Storage::fake('local');
    Notification::fake();
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.transport' => 'smtp']);
    $this->post(route('portal.new-resident.store'), newResidentApplicationPayload())->assertRedirect(route('portal.new-resident.status'))->assertSessionHasNoErrors();

    $application = ResidentRegistrationApplication::query()->sole();
    expect($application->status)->toBe('pending');
    Notification::assertNothingSent();
    $this->assertDatabaseEmpty('residents');
    $this->assertDatabaseEmpty('users');
    Storage::disk('local')->assertExists($application->photo_path);
    $this->withCookie(config('session.cookie'), session()->getId())->get(route('portal.new-resident.status'))->assertSee($application->reference)->assertSee('Pending review');
    $this->get(route('portal.request.create'))->assertRedirect();
    $this->get(route('staff.resident-applications.photo', $application))->assertRedirect(route('login'));
});

test('a new registration never reuses an approved application and reaches the admin review queue', function () {
    Storage::fake('local');
    $previous = ResidentRegistrationApplication::factory()->create(['status' => 'approved']);
    $this->withSession(['new_resident_application' => $previous->id]);

    $response = $this->get(route('portal.new-resident.create'))->assertOk()->assertSessionMissing('new_resident_application');
    $response->assertSee('name="first_name"', false)->assertSee('name="date_of_birth"', false)->assertSee('name="photo"', false)
        ->assertDontSee($previous->reference)->assertDontSee('Your resident registration is approved.');
    $this->post(route('portal.new-resident.store'), newResidentApplicationPayload())->assertRedirect(route('portal.new-resident.status'))->assertSessionHasNoErrors();
    $application = ResidentRegistrationApplication::where('status', 'pending')->sole();
    expect($application->id)->not->toBe($previous->id);
    expect($application->reference)->not->toBe($previous->reference);
    expect($previous->fresh()->status)->toBe('approved');
    $this->withCookie(config('session.cookie'), session()->getId());
    for ($refresh = 0; $refresh < 2; $refresh++) {
        $this->get(route('portal.new-resident.status'))->assertSee($application->reference)->assertSee('Pending review')->assertDontSee($previous->reference);
    }
    $this->assertDatabaseCount('resident_registration_applications', 2);
    $this->actingAs(User::factory()->superAdmin()->create())->get(route('staff.residents.index'))->assertViewHas('pendingRegistrationsCount', 1)->assertSee('1 pending');
    $this->get(route('staff.resident-applications.index'))->assertSee($application->reference)->assertSee($application->email);
});

test('status without a submitting session returns to details and validation preserves the new form', function () {
    $this->get(route('portal.new-resident.status'))->assertRedirect(route('portal.new-resident.create'));
    $this->from(route('portal.new-resident.create'))->post(route('portal.new-resident.store'), ['first_name' => 'Draft name'])->assertSessionHasErrors('last_name');
    $this->withCookie(config('session.cookie'), session()->getId())->get(route('portal.new-resident.create'))->assertSee('value="Draft name"', false)->assertSee('name="last_name"', false);
    $this->assertDatabaseEmpty('resident_registration_applications');
});

test('staff without review permission receive no pending application count or shortcut', function () {
    ResidentRegistrationApplication::factory()->create();
    $staff = User::factory()->create(['role' => 'staff', 'staff_permissions' => ['records.view']]);
    $this->actingAs($staff)->get(route('staff.residents.index'))->assertViewHas('pendingRegistrationsCount', 0)->assertDontSee('Review new resident applications');
    $this->get(route('staff.resident-applications.index'))->assertForbidden();
});

test('existing resident is directed to activation instead of the new resident queue', function () {
    Storage::fake('local');
    Resident::factory()->create(['first_name' => 'Maria', 'middle_name' => 'Cruz', 'last_name' => 'Newresident', 'suffix' => null, 'date_of_birth' => '1996-02-10']);
    $this->post(route('portal.new-resident.store'), newResidentApplicationPayload())->assertSessionHasErrors('first_name');

    $this->assertDatabaseEmpty('resident_registration_applications');
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('duplicate pending applications do not create a second entry or orphan photo', function () {
    Storage::fake('local');
    $payload = newResidentApplicationPayload();
    $this->post(route('portal.new-resident.store'), $payload)->assertSessionHasNoErrors();
    $this->flushSession();
    $this->post(route('portal.new-resident.store'), newResidentApplicationPayload())->assertSessionHasErrors('application');

    $this->assertDatabaseCount('resident_registration_applications', 1);
    expect(Storage::disk('local')->allFiles())->toHaveCount(1);
});

test('application rejects private administrative fields and nonimage uploads', function () {
    Storage::fake('local');
    $this->post(route('portal.new-resident.store'), newResidentApplicationPayload([
        'photo' => UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'), 'status' => 'approved',
        'reviewed_by' => 1, 'role' => 'admin', 'is_active' => true, 'password' => 'NotAStoredPassword',
    ]))->assertSessionHasErrors(['photo', 'status', 'reviewed_by', 'role', 'is_active', 'password']);
    $this->assertDatabaseEmpty('resident_registration_applications');
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('only assigned reviewers and superadmin can browse review and private photos', function () {
    Storage::fake('local');
    $application = ResidentRegistrationApplication::factory()->create();
    Storage::disk('local')->put($application->photo_path, 'private photo');
    foreach ([User::factory()->create(['role' => 'staff', 'staff_permissions' => ['records.view']]), User::factory()->create(['role' => 'staff', 'staff_permissions' => ['incidents.submit']]), User::factory()->resident()->create()] as $user) {
        $this->actingAs($user)->get(route('staff.resident-applications.index'))->assertForbidden();
        $this->get(route('staff.resident-applications.photo', $application))->assertForbidden();
        $this->post(route('staff.resident-applications.update', $application), ['decision' => 'approved'])->assertForbidden();
    }
    foreach ([User::factory()->create(['role' => 'staff', 'staff_permissions' => ['records.view', 'records.review']]), User::factory()->superAdmin()->create()] as $reviewer) {
        $this->actingAs($reviewer)->get(route('staff.resident-applications.index'))->assertSee($application->reference);
        $this->get(route('staff.resident-applications.photo', $application))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }
});

test('approval creates exactly one resident without an account or activation code', function () {
    Storage::fake('local');
    Notification::fake();
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.transport' => 'smtp']);
    $reviewer = User::factory()->superAdmin()->create();
    $application = ResidentRegistrationApplication::factory()->create(['locale' => 'fil']);
    $this->actingAs($reviewer)->post(route('staff.resident-applications.update', $application), ['decision' => 'approved'])
        ->assertRedirect(route('staff.resident-applications.index'))->assertSessionHasNoErrors();

    $resident = Resident::query()->sole();
    expect($application->fresh()->status)->toBe('approved');
    expect($application->fresh()->reviewed_by)->toBe($reviewer->id);
    expect($resident->status)->toBe('active');
    expect($resident->portal_registration_email)->toBeNull();
    expect($resident->portal_registration_hash)->toBeNull();
    expect($resident->portal_registration_sent_at)->toBeNull();
    expect($resident->portalAccount()->exists())->toBeFalse();
    Notification::assertSentOnDemand(ResidentRegistrationReviewedNotification::class);
    Notification::assertNotSentTo(new AnonymousNotifiable, ResidentPortalActivationNotification::class);
    $this->assertDatabaseHas('administrative_audits', ['user_id' => $reviewer->id, 'action' => 'admin.resident-applications.approved']);
    $this->post(route('staff.resident-applications.update', $application), ['decision' => 'approved'])->assertConflict();
    $this->assertDatabaseCount('residents', 1);

});

test('correction is visible only to the submitting session and can be resubmitted', function () {
    Storage::fake('local');
    $this->post(route('portal.new-resident.store'), newResidentApplicationPayload());
    $application = ResidentRegistrationApplication::query()->sole();
    $oldPath = $application->photo_path;
    $sessionId = session()->getId();
    $reviewer = User::factory()->superAdmin()->create();
    $this->actingAs($reviewer)->post(route('staff.resident-applications.update', $application), ['decision' => 'needs_correction', 'remarks' => 'Please provide your complete address.'])->assertSessionHasNoErrors();
    $this->withCookie(config('session.cookie'), $sessionId)->get(route('portal.new-resident.status'))->assertSee('Please provide your complete address.');
    $this->post(route('portal.new-resident.store'), newResidentApplicationPayload(['address' => 'Corrected complete address']))->assertSessionHasNoErrors();
    expect($application->fresh()->status)->toBe('pending');
    expect($application->fresh()->details['address'])->toBe('Corrected complete address');
    Storage::disk('local')->assertMissing($oldPath);
    $this->assertDatabaseCount('resident_registration_applications', 1);
    $this->flushSession();
    $this->withCookie(config('session.cookie'), str_repeat('x', 40))->get(route('portal.new-resident.create'))->assertDontSee($application->reference)->assertDontSee('Please provide your complete address.');
});

test('decline requires a reason and creates no resident record', function () {
    $application = ResidentRegistrationApplication::factory()->create();
    $this->actingAs(User::factory()->superAdmin()->create())->post(route('staff.resident-applications.update', $application), ['decision' => 'declined'])->assertSessionHasErrors('remarks');
    $this->post(route('staff.resident-applications.update', $application), ['decision' => 'declined', 'remarks' => 'Please ask staff to verify the submitted details.'])->assertSessionHasNoErrors();
    expect($application->fresh()->status)->toBe('declined');
    $this->assertDatabaseEmpty('residents');
});

test('revocation blocks review and actor fields cannot be forged', function () {
    $application = ResidentRegistrationApplication::factory()->create();
    $reviewer = User::factory()->create(['role' => 'staff', 'staff_permissions' => ['records.view', 'records.review']]);
    $this->actingAs($reviewer)->post(route('staff.resident-applications.update', $application), ['decision' => 'approved', 'reviewed_by' => 999])->assertSessionHasErrors('reviewed_by');
    $reviewer->forceFill(['staff_permissions' => ['records.view']])->save();
    $this->actingAs($reviewer->fresh())->post(route('staff.resident-applications.update', $application), ['decision' => 'approved'])->assertForbidden();
    $this->assertDatabaseEmpty('residents');
    expect($application->fresh()->status)->toBe('pending');
});

test('a resident record added during review prevents duplicate approval', function () {
    $application = ResidentRegistrationApplication::factory()->create();
    Resident::factory()->create($application->details);
    $this->actingAs(User::factory()->superAdmin()->create())->post(route('staff.resident-applications.update', $application), ['decision' => 'approved'])->assertSessionHasErrors('decision');
    $this->assertDatabaseCount('residents', 1);
    expect($application->fresh()->status)->toBe('pending');
});

test('new application form supports both portal languages and preserves existing registration', function () {
    $this->get(route('portal.register'))->assertSee('Apply as a new resident')->assertSee('Find resident record');
    $this->get(route('portal.new-resident.create'))->assertSee('New resident registration');
    $this->withCookie('portal_locale', 'fil')->get(route('portal.new-resident.create'))->assertSee('Pagpaparehistro ng bagong residente');
    expect(StaffPermissions::keys())->toContain('records.review');
    expect(User::factory()->create(['role' => 'staff', 'staff_permissions' => ['records.view']])->hasPermission('records.review'))->toBeFalse();
});

test('new resident entry sits inside the registration card and is translated', function (string $locale) {
    $response = $this->withCookie('portal_locale', $locale)->get(route('portal.register'))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//section[contains(@class,"card")]/div[contains(@class,"card-body")]/div[@class="registration-path-option"]//a[@href="'.route('portal.new-resident.create').'" and contains(@class,"btn")]'))->toHaveCount(1);
    $response->assertSee($locale === 'fil' ? 'Wala pang resident record?' : 'No resident record yet?');
})->with(['en', 'fil']);

test('registration groups details into accessible sections and preserves validation drafts safely', function () {
    $response = $this->get(route('portal.new-resident.create'))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//form[@data-resident-form]/fieldset/legend'))->toHaveCount(4);
    foreach ($xpath->query('//form[@data-resident-form]//input[not(@type="hidden")]|//form[@data-resident-form]//select|//form[@data-resident-form]//textarea') as $field) {
        expect($xpath->query('//label[@for="'.$field->getAttribute('id').'"]'))->toHaveCount(1);
    }
    $response->assertSee('Personal information')->assertSee('Residential address')->assertSee('Contact details')->assertSee('What happens next');
    $this->from(route('portal.new-resident.create'))->post(route('portal.new-resident.store'), ['first_name' => '<script>alert("draft")</script>', 'last_name' => 'Draft lastname', 'date_of_birth' => ['invalid']])->assertSessionHasErrors(['first_name', 'date_of_birth']);
    $response = $this->withCookie(config('session.cookie'), session()->getId())->get(route('portal.new-resident.create'))->assertOk();
    $response->assertSee('value="Draft lastname"', false)->assertDontSee('<script>alert("draft")</script>', false)->assertSee('aria-invalid="true"', false);
});

test('review interface separates the private photo details status and decision controls', function () {
    $application = ResidentRegistrationApplication::factory()->create();
    $response = $this->actingAs(User::factory()->superAdmin()->create())->get(route('staff.resident-applications.index'))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//details[@open]/summary//span[@data-application-state="pending"]'))->toHaveCount(1);
    expect($xpath->query('//figure/img[@src="'.route('staff.resident-applications.photo', $application).'"]'))->toHaveCount(1);
    expect($xpath->query('//form[@action="'.route('staff.resident-applications.update', $application).'" and @method="POST"]//select[@name="decision"]'))->toHaveCount(1);
    $response->assertSee('Review decision')->assertSee('Required when requesting correction or declining an application.');
});

test('assigned reviewer can approve without granting itself separate activation access', function () {
    Notification::fake();
    config(['mail.default' => 'array', 'mail.mailers.array.transport' => 'array']);
    $application = ResidentRegistrationApplication::factory()->create();
    $reviewer = User::factory()->create(['role' => 'staff', 'staff_permissions' => ['records.view', 'records.review']]);

    $this->actingAs($reviewer)->post(route('staff.resident-applications.update', $application), ['decision' => 'approved'])->assertSessionHasNoErrors();
    $resident = Resident::query()->sole();
    expect($resident->portal_registration_hash)->toBeNull();
    $this->post(route('staff.residents.portal-activation', $resident))->assertForbidden();
    Notification::assertSentOnDemand(ResidentRegistrationReviewedNotification::class);
    Notification::assertNotSentTo(new AnonymousNotifiable, ResidentPortalActivationNotification::class);
    $this->assertDatabaseCount('users', 1);
});

test('new application validation follows the selected resident language', function () {
    $this->withCookie('portal_locale', 'fil')->post(route('portal.new-resident.store'), [])->assertSessionHasErrors('email');
    $this->withCookie(config('session.cookie'), session()->getId())->get(route('portal.new-resident.create'))->assertSee('Kailangan ang email.');
    $this->assertDatabaseEmpty('resident_registration_applications');
});

test('approval queues a result without issuing an activation code', function () {
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.transport' => 'smtp']);
    Notification::fake();
    $application = ResidentRegistrationApplication::factory()->create();

    $this->actingAs(User::factory()->superAdmin()->create())->post(route('staff.resident-applications.update', $application), ['decision' => 'approved'])->assertSessionHasNoErrors();
    expect($application->fresh()->status)->toBe('approved');
    $this->assertDatabaseCount('residents', 1);
    $this->assertDatabaseHas('administrative_audits', ['action' => 'admin.resident-applications.approved']);
    Notification::assertSentOnDemand(ResidentRegistrationReviewedNotification::class, fn ($notification): bool => $notification->afterCommit === true && $notification instanceof ShouldQueue);
    Notification::assertNotSentTo(new AnonymousNotifiable, ResidentPortalActivationNotification::class);
});

test('review result email reaches the application email with reference decision and reason', function (string $decision, string $locale) {
    Notification::fake();
    $application = ResidentRegistrationApplication::factory()->create(['locale' => $locale]);
    $remarks = 'Please verify the complete address. <script>do-not-execute</script>';
    $this->actingAs(User::factory()->superAdmin()->create())->post(route('staff.resident-applications.update', $application), ['decision' => $decision, 'remarks' => $remarks])->assertSessionHasNoErrors();
    Notification::assertSentOnDemand(ResidentRegistrationReviewedNotification::class, function ($notification, $channels, $recipient) use ($application, $decision, $remarks, $locale): bool {
        return $recipient->routes['mail'] === $application->email && $notification->reference === $application->reference
            && $notification->decision === $decision && $notification->remarks === $remarks
            && $notification->locale === $locale && $channels === ['mail'] && $notification->afterCommit === true;
    });
    $notification = Notification::sent(new AnonymousNotifiable, ResidentRegistrationReviewedNotification::class)->sole();
    app()->setLocale($locale);
    $mail = $notification->toMail(new AnonymousNotifiable);
    if ($decision === 'approved') {
        expect($mail->viewData['actionUrl'])->toBe(route('portal.register', ['step' => 'activation']));
    }
    $html = view($mail->view['html'], $mail->viewData)->render();
    $text = view($mail->view['text'], $mail->viewData)->render();
    expect($html)->toContain($application->reference, 'Please verify the complete address.', '&lt;script&gt;')
        ->not->toContain('<script>do-not-execute</script>', 'Activation Code');
    expect($text)->toContain($application->reference, 'Please verify the complete address.');
    expect($mail->subject)->toContain($locale === 'fil' ? 'Resulta ng pagpaparehistro ng residente' : 'Resident registration update');
    $this->post(route('staff.resident-applications.update', $application), ['decision' => $decision, 'remarks' => $remarks])->assertConflict();
    expect(Notification::sent(new AnonymousNotifiable, ResidentRegistrationReviewedNotification::class))->toHaveCount(1);
    Notification::assertNotSentTo(new AnonymousNotifiable, ResidentPortalActivationNotification::class);
})->with([
    ['approved', 'en'], ['approved', 'fil'], ['declined', 'en'], ['declined', 'fil'],
    ['needs_correction', 'en'], ['needs_correction', 'fil'],
]);

test('invalid or unauthorized reviews send no result emails', function () {
    Notification::fake();
    $application = ResidentRegistrationApplication::factory()->create();
    $this->actingAs(User::factory()->create(['role' => 'staff', 'staff_permissions' => ['records.view']]))
        ->post(route('staff.resident-applications.update', $application), ['decision' => 'approved'])->assertForbidden();
    $this->actingAs(User::factory()->superAdmin()->create())->post(route('staff.resident-applications.update', $application), ['decision' => 'declined'])->assertSessionHasErrors('remarks');
    Resident::factory()->create($application->details);
    $this->post(route('staff.resident-applications.update', $application), ['decision' => 'approved'])->assertSessionHasErrors('decision');
    Notification::assertNothingSent();
    expect($application->fresh()->status)->toBe('pending');
});

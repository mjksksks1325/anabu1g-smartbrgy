<?php

use App\Http\Controllers\ResidentRegistrationController;
use App\Models\Resident;
use App\Models\User;
use App\Notifications\ResidentEmailVerificationNotification;
use App\Notifications\ResidentPortalActivationNotification;
use Illuminate\Http\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Mailer\Exception\TransportException;

/*
 * Resident Portal activation is staff-verified only. The former self-service path (full name → birth date →
 * code sent to any email) let anyone who knew a resident's name and birth date take over that record; it was
 * removed. These tests pin the closed path, the single-use staff code, and email confirmation before requests.
 */

function staffCodeResident(string $code = 'ABCD2345', array $attributes = []): Resident
{
    return Resident::factory()->create([
        'first_name' => 'Jane', 'middle_name' => 'A', 'last_name' => 'Santos', 'date_of_birth' => '1990-02-03', 'status' => 'active',
        'portal_registration_hash' => hash('sha256', $code), 'portal_registration_expires_at' => now()->addDay(),
        ...$attributes,
    ]);
}

function activationAttempt(Resident|string $resident, string $code): array
{
    return ['resident_number' => $resident instanceof Resident ? $resident->resident_number : $resident, 'activation_code' => $code];
}

test('the removed self-service steps cannot be reached and look up no resident', function (string $path, array $input) {
    Notification::fake();
    config(['mail.default' => 'smtp']);
    Resident::factory()->create(['first_name' => 'Jane', 'middle_name' => 'A', 'last_name' => 'Santos', 'date_of_birth' => '1990-02-03', 'status' => 'active']);

    $real = $this->post($path, $input)->assertNotFound();
    $fake = $this->post($path, ['full_name' => 'Nobody Here', 'date_of_birth' => '2000-01-01', 'email' => 'other@example.test'])->assertNotFound();

    expect($real->getContent())->toBe($fake->getContent());
    expect(Resident::query()->whereNotNull('portal_registration_hash')->exists())->toBeFalse();
    Notification::assertNothingSent();
    $this->assertDatabaseEmpty('users');
})->with([
    'name step' => ['/portal/register/name', ['full_name' => 'Jane A Santos']],
    'birth date step' => ['/portal/register/confirm-record', ['date_of_birth' => '1990-02-03']],
    'email code step' => ['/portal/register/send-code', ['email' => 'attacker@example.test']],
]);

test('a forged session from the old flow cannot obtain a code or an account', function () {
    Notification::fake();
    $resident = Resident::factory()->create(['status' => 'active']);
    $this->withSession(['resident_identity' => ['resident_id' => $resident->id, 'expires_at' => now()->addMinutes(10)->timestamp]])
        ->post('/portal/register/send-code', ['email' => 'attacker@example.test'])->assertNotFound();
    $this->withSession(['resident_identity' => ['resident_id' => $resident->id, 'expires_at' => now()->addMinutes(10)->timestamp]])
        ->post(route('portal.register.store'), ['email' => 'attacker@example.test', 'password' => 'ResidentPassword123!', 'password_confirmation' => 'ResidentPassword123!'])
        ->assertRedirect(route('portal.registration.denied'));

    expect($resident->fresh()->portal_registration_hash)->toBeNull();
    $this->assertDatabaseEmpty('users');
    Notification::assertNothingSent();
});

test('the registration page offers only the staff-issued activation code path', function (string $locale) {
    // Step 1 only explains eligibility: it has no fields at all, so nothing can be looked up from it.
    $eligibility = $this->withCookie('portal_locale', $locale)->get(route('portal.register'))->assertOk()->assertViewHas('stage', 'eligibility');
    expect(substr_count($eligibility->getContent(), 'name="resident_number"') + substr_count($eligibility->getContent(), 'name="full_name"') + substr_count($eligibility->getContent(), 'name="date_of_birth"'))->toBe(0);
    $eligibility->assertSee(route('portal.register', ['step' => 'verify']), false)->assertSee(route('portal.new-resident.create'), false);

    $response = $this->withCookie('portal_locale', $locale)->get(route('portal.register', ['step' => 'verify']))->assertOk()->assertViewHas('stage', 'code');
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $fields = collect($xpath->query('//section[contains(@class,"rp-form-card")]//form//input[not(@type="hidden")]'))->map(fn (DOMElement $input): string => $input->getAttribute('name'))->all();

    expect($fields)->toBe(['resident_number', 'activation_code'])
        ->and($xpath->query('//input[@name="full_name" or @name="date_of_birth" or @name="photo"]'))->toHaveCount(0)
        ->and($xpath->query('//label[@for="resident-number"]'))->toHaveCount(1)
        ->and($xpath->query('//label[@for="activation-code"]'))->toHaveCount(1);
    $response->assertSee($locale === 'fil' ? 'Ive-verify ng awtorisadong barangay staff ang identity ninyo.' : 'Authorized barangay staff will verify your identity.')
        ->assertSee($locale === 'fil' ? 'Isang beses lang ito magagamit at valid nang 24 oras.' : 'The code works once and is valid for 24 hours.')
        ->assertSee(route('portal.new-resident.create'));
})->with(['en', 'fil']);

test('every refused code looks the same, so a resident number cannot be confirmed', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    $withCode = staffCodeResident();
    $withoutCode = Resident::factory()->create(['status' => 'active']);
    $expired = staffCodeResident('EXPIRE11', ['portal_registration_expires_at' => now()->subMinute()]);
    $inactive = staffCodeResident('INACTIVE', ['status' => 'inactive']);

    $responses = collect([
        activationAttempt('ANB-2026-DOESNOTEXIST', 'ABCD2345'),
        activationAttempt($withoutCode, 'ABCD2345'),
        activationAttempt($withCode, 'WRONG999'),
        activationAttempt($expired, 'EXPIRE11'),
        activationAttempt($inactive, 'INACTIVE'),
    ])->map(function (array $attempt): array {
        $response = $this->post(route('portal.register.verify'), $attempt);

        return [$response->getStatusCode(), $response->headers->get('Location'), session()->has('existing_account'), session()->has('resident_verification')];
    });

    expect($responses->unique()->values()->all())->toBe([[302, route('portal.registration.denied'), false, false]]);
    $this->get(route('portal.registration.denied'))->assertOk()
        ->assertDontSee($withCode->full_name)->assertDontSee('1990-02-03')->assertDontSee($withCode->resident_number);
});

test('a code stops working after five wrong attempts, and attempts are audited without the code', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    $resident = staffCodeResident('RIGHT234');

    for ($attempt = 1; $attempt <= ResidentRegistrationController::MAX_FAILED_ATTEMPTS; $attempt++) {
        $this->post(route('portal.register.verify'), activationAttempt($resident, 'WRONG'.$attempt))->assertRedirect(route('portal.registration.denied'));
    }
    $this->post(route('portal.register.verify'), activationAttempt($resident, 'RIGHT234'))
        ->assertRedirect(route('portal.registration.denied'))->assertSessionMissing('resident_verification');

    expect($resident->fresh()->portal_registration_hash)->toBeNull();
    expect(DB::table('administrative_audits')->where('action', 'portal.activation.failed')->where('record', 'resident:'.$resident->id)->count())->toBe(5);
    $this->assertDatabaseHas('administrative_audits', ['action' => 'portal.activation.locked', 'record' => 'resident:'.$resident->id]);
    expect(DB::table('administrative_audits')->get()->toJson())->not->toContain('RIGHT234')->not->toContain('WRONG1')->not->toContain(hash('sha256', 'RIGHT234'));
});

test('the public verification step is rate limited per address', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('portal.register.verify'), activationAttempt('UNKNOWN', 'UNKNOWN1'))->assertRedirect();
    }
    $this->post(route('portal.register.verify'), activationAttempt('UNKNOWN', 'UNKNOWN1'))->assertTooManyRequests();
});

test('an expired code is rejected, cleared and audited', function () {
    $resident = staffCodeResident('LATE2345');
    $this->travel(25)->hours();

    $this->post(route('portal.register.verify'), activationAttempt($resident, 'LATE2345'))->assertRedirect(route('portal.registration.denied'));

    expect($resident->fresh()->portal_registration_hash)->toBeNull();
    $this->assertDatabaseHas('administrative_audits', ['action' => 'portal.activation.expired', 'record' => 'resident:'.$resident->id]);
});

test('a code sent by the removed email self-service step no longer activates an account', function () {
    $resident = staffCodeResident('EMAIL234', ['portal_registration_sent_at' => now()->subHour(), 'portal_registration_email' => 'attacker@example.test']);

    $this->post(route('portal.register.verify'), activationAttempt($resident, 'EMAIL234'))
        ->assertRedirect(route('portal.registration.denied'))->assertSessionMissing('resident_verification');

    expect($resident->fresh())->portal_registration_hash->toBeNull()->portal_registration_email->toBeNull();
    $this->assertDatabaseHas('administrative_audits', ['action' => 'portal.activation.self-service-code-rejected', 'record' => 'resident:'.$resident->id]);
    $this->assertDatabaseEmpty('users');
});

test('two sessions holding the same code cannot both claim the record', function () {
    Notification::fake();
    $resident = staffCodeResident('SHARED23');
    $this->post(route('portal.register.verify'), activationAttempt($resident, 'SHARED23'))->assertRedirect(route('portal.register'));
    $proof = session('resident_verification');
    $input = ['password' => 'ResidentPassword123!', 'password_confirmation' => 'ResidentPassword123!'];

    $this->post(route('portal.register.store'), ['email' => 'first@example.test', ...$input])->assertRedirect(route('portal.login'));
    $this->withSession(['resident_verification' => $proof])->post(route('portal.register.store'), ['email' => 'second@example.test', ...$input])
        ->assertRedirect(route('portal.registration.denied'));

    expect(User::query()->where('resident_id', $resident->id)->pluck('email')->all())->toBe(['first@example.test']);
});

test('staff need the activation permission and an in-person ID confirmation to issue a code', function () {
    $resident = Resident::factory()->create(['status' => 'active']);
    $this->actingAs(User::factory()->create(['role' => 'staff', 'staff_permissions' => ['records.view', 'records.update']]))
        ->postJson(route('staff.residents.portal-activation', $resident), ['identity_checked' => true])->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'staff', 'staff_permissions' => ['records.view', 'records.activate']]))
        ->postJson(route('staff.residents.portal-activation', $resident), ['identity_checked' => false])->assertJsonValidationErrors('identity_checked');
    expect($resident->fresh()->portal_registration_hash)->toBeNull();

    $code = $this->postJson(route('staff.residents.portal-activation', $resident), ['identity_checked' => true])->assertOk()->json('activation_code');
    Notification::fake();
    $this->post(route('portal.register.verify'), activationAttempt($resident, $code))->assertRedirect(route('portal.register'));
    $this->post(route('portal.register.store'), ['email' => 'jane@example.test', 'password' => 'ResidentPassword123!', 'password_confirmation' => 'ResidentPassword123!'])
        ->assertRedirect(route('portal.login'));
    expect($resident->fresh()->portalAccount()->sole()->email)->toBe('jane@example.test');
    Notification::assertNotSentTo(new AnonymousNotifiable, ResidentPortalActivationNotification::class);
});

test('an unconfirmed email cannot request documents, and confirming it opens requests', function () {
    $account = User::factory()->resident()->create(['email_verified_at' => null]);
    $request = ['document_type' => 'Certificate of Residency', 'purpose' => 'Employment'];

    $this->actingAs($account, 'resident')->get(route('portal.request.create'))->assertForbidden()
        ->assertSee('Confirm your email address before requesting documents')->assertSee(route('portal.verification.send'));
    $this->postJson(route('portal.request.store'), $request)->assertForbidden();
    $this->assertDatabaseEmpty('document_requests');

    $this->get(ResidentEmailVerificationNotification::verificationUrl($account))->assertRedirect(route('portal.request.create'));
    expect($account->fresh()->email_verified_at)->not->toBeNull();
    $this->assertDatabaseHas('administrative_audits', ['action' => 'portal.email.verified', 'user_id' => $account->id]);
    $this->postJson(route('portal.request.store'), $request)->assertOk();
    $this->assertDatabaseCount('document_requests', 1);
});

test('confirmation links must be signed, unexpired and match the account email', function (string $case) {
    $account = User::factory()->resident()->create(['email_verified_at' => null, 'email' => 'jane@example.test']);
    $staff = User::factory()->assignedOperations()->create(['email_verified_at' => null]);
    $url = match ($case) {
        'unsigned' => route('portal.verification.verify', ['account' => $account->id, 'hash' => sha1('jane@example.test')]),
        'altered signature' => ResidentEmailVerificationNotification::verificationUrl($account).'0',
        'other email' => ResidentEmailVerificationNotification::verificationUrl($account->replicate()->forceFill(['id' => $account->id, 'email' => 'old@example.test'])),
        'expired' => ResidentEmailVerificationNotification::verificationUrl($account),
        'staff account' => ResidentEmailVerificationNotification::verificationUrl($staff),
        'missing account' => ResidentEmailVerificationNotification::verificationUrl($account->replicate()->forceFill(['id' => 999999, 'email' => 'jane@example.test'])),
    };
    if ($case === 'expired') {
        $this->travel(61)->minutes();
    }

    $this->get($url)->assertRedirect(route('portal.login'))
        ->assertSessionHasErrors(['verification' => 'This confirmation link is invalid or has expired. Sign in and select Send a new confirmation link.']);
    expect($account->fresh()->email_verified_at)->toBeNull()
        ->and($staff->fresh()->email_verified_at)->toBeNull();
    $this->assertDatabaseMissing('administrative_audits', ['action' => 'portal.email.verified']);
})->with(['unsigned', 'altered signature', 'other email', 'expired', 'staff account', 'missing account']);

test('a rejected link explains itself on the page instead of an error page', function () {
    $account = User::factory()->resident()->create(['email_verified_at' => null]);
    $url = ResidentEmailVerificationNotification::verificationUrl($account);
    $this->travel(61)->minutes();

    $this->followingRedirects()->get($url)->assertOk()->assertSee('This confirmation link is invalid or has expired.');
    $this->actingAs($account, 'resident')->get($url)->assertRedirect(route('portal.request.create'))->assertSessionHasErrors('verification');
});

test('a confirmation link works with a staff session open in the same browser, and again after use', function () {
    $account = User::factory()->resident()->create(['email_verified_at' => null]);
    $url = ResidentEmailVerificationNotification::verificationUrl($account);

    $this->actingAs(User::factory()->assignedOperations()->create(), 'web')->get($url)
        ->assertRedirect(route('portal.login'))->assertSessionHas('status', 'Your email address is confirmed. You can now request documents.');
    $verifiedAt = $account->fresh()->email_verified_at;
    expect($verifiedAt)->not->toBeNull();

    $this->travel(5)->minutes();
    $this->get($url)->assertRedirect(route('portal.login'))->assertSessionHas('status');
    expect($account->fresh()->email_verified_at->equalTo($verifiedAt))->toBeTrue();
    expect(DB::table('administrative_audits')->where('action', 'portal.email.verified')->count())->toBe(1);
});

test('repeated confirmation link attempts are rate limited with a message', function () {
    $url = ResidentEmailVerificationNotification::verificationUrl(User::factory()->resident()->create(['email_verified_at' => null]));

    for ($attempt = 0; $attempt < 6; $attempt++) {
        $this->get($url.'0')->assertSessionHasErrors('verification');
    }

    $this->get($url)->assertRedirect(route('portal.login'))
        ->assertSessionHasErrors(['verification' => 'Too many attempts. Please wait before trying again.']);
});

test('the plain-text confirmation email carries a working link', function () {
    $account = User::factory()->resident()->create(['email_verified_at' => null]);
    $url = ResidentEmailVerificationNotification::verificationUrl($account);

    $text = view('mail.resident-email-verification-text', ['verificationUrl' => $url, 'minutes' => 60])->render();

    expect($url)->toContain('&signature=')
        ->and($text)->toContain($url)->not->toContain('&amp;');
});

test('a mail server failure while sending a confirmation link is explained, not a server error', function () {
    Exceptions::fake();
    useFailingMailer();
    $account = User::factory()->resident()->create(['email_verified_at' => null]);

    $this->actingAs($account, 'resident')->from(route('portal.request.create'))->post(route('portal.verification.send'))
        ->assertRedirect(route('portal.request.create'))
        ->assertSessionHasErrors(['verification' => 'We could not send the confirmation email right now. Please try again in a few minutes. If it keeps failing, ask the Barangay Hall for help.'])
        ->assertSessionMissing('status');
    Exceptions::assertReported(TransportException::class);
});

test('a new account is told when its first confirmation email could not be sent', function () {
    Exceptions::fake();
    useFailingMailer();
    $resident = staffCodeResident();
    $this->post(route('portal.register.verify'), activationAttempt($resident, 'ABCD2345'))->assertRedirect(route('portal.register'));

    $this->post(route('portal.register.store'), ['email' => 'jane@example.test', 'password' => 'ResidentPassword123!', 'password_confirmation' => 'ResidentPassword123!'])
        ->assertRedirect(route('portal.login'))
        ->assertSessionHas('status', 'Your resident account is ready, but we could not send the confirmation email. Sign in and select Send a new confirmation link.');
    expect($resident->fresh()->portalAccount()->sole()->email_verified_at)->toBeNull();
    Exceptions::assertReported(TransportException::class);
});

test('the resend limit belongs to each resident account, not to a shared network address', function () {
    Notification::fake();
    [$first, $second] = User::factory()->resident()->count(2)->create(['email_verified_at' => null]);
    // Sign in through the portal form, as residents do (actingAs() would also make the resident guard the default).
    $this->post(route('portal.login.store'), ['email' => $first->email, 'password' => 'password'])->assertRedirect();

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $this->post(route('portal.verification.send'))->assertSessionHasNoErrors();
    }
    $this->post(route('portal.verification.send'))->assertSessionHasErrors(['verification' => 'Too many attempts. Please wait before trying again.']);

    $this->post(route('portal.logout'));
    $this->post(route('portal.login.store'), ['email' => $second->email, 'password' => 'password'])->assertRedirect();
    $this->post(route('portal.verification.send'))->assertSessionHasNoErrors()->assertSessionHas('status');
    Notification::assertSentToTimes($first, ResidentEmailVerificationNotification::class, 3);
    Notification::assertSentToTimes($second, ResidentEmailVerificationNotification::class, 1);

    // On a live server the route throttle runs before the resident is attached to the request, so a plain
    // `throttle:3,1` counts per IP address; the named limiter reads the resident guard itself.
    expect(Route::getRoutes()->getByName('portal.verification.send')->gatherMiddleware())->toContain('throttle:resident-confirmation-email');
    Auth::guard('resident')->setUser($second);
    $limit = RateLimiter::limiter('resident-confirmation-email')(Request::create('/portal/email/verification-notification', 'POST', server: ['REMOTE_ADDR' => '203.0.113.5']));
    expect($limit->key)->toBe('resident-confirmation-email:account:'.$second->id)->and($limit->maxAttempts)->toBe(3);
});

test('a server that does not deliver email never claims a confirmation link was sent, or logs the link', function () {
    Notification::fake();
    config(['mail.default' => 'log']);
    $resident = staffCodeResident();
    $this->post(route('portal.register.verify'), activationAttempt($resident, 'ABCD2345'))->assertRedirect(route('portal.register'));

    $this->post(route('portal.register.store'), ['email' => 'jane@example.test', 'password' => 'ResidentPassword123!', 'password_confirmation' => 'ResidentPassword123!'])
        ->assertRedirect(route('portal.login'))
        ->assertSessionHas('status', 'Your resident account is ready, but we could not send the confirmation email. Sign in and select Send a new confirmation link.');
    $account = $resident->fresh()->portalAccount()->sole();

    $this->actingAs($account, 'resident')->from(route('portal.request.create'))->post(route('portal.verification.send'))
        ->assertRedirect(route('portal.request.create'))
        ->assertSessionHasErrors(['verification' => 'We could not send the confirmation email right now. Please try again in a few minutes. If it keeps failing, ask the Barangay Hall for help.'])
        ->assertSessionMissing('status');

    Notification::assertNothingSent();
    expect(DB::table('administrative_audits')->where('action', 'portal.email.confirmation-mail-unavailable')->where('record', 'resident:'.$resident->id)->count())->toBe(2)
        ->and($account->fresh()->email_verified_at)->toBeNull();
});

test('a mail server failure while sending a confirmation link is recorded for administrators', function () {
    Exceptions::fake();
    useFailingMailer();
    $account = User::factory()->resident()->create(['email_verified_at' => null]);

    $this->actingAs($account, 'resident')->post(route('portal.verification.send'))->assertSessionHasErrors('verification');

    $this->assertDatabaseHas('administrative_audits', ['action' => 'portal.email.confirmation-mail-failed', 'user_id' => $account->id, 'record' => 'resident:'.$account->resident_id]);
});

test('a signed-in resident can ask for a new confirmation link, within a limit', function () {
    Notification::fake();
    $account = User::factory()->resident()->create(['email_verified_at' => null]);

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $this->actingAs($account, 'resident')->post(route('portal.verification.send'))->assertRedirect();
    }
    $this->post(route('portal.verification.send'))->assertRedirect(route('portal.request.create'))
        ->assertSessionHasErrors(['verification' => 'Too many attempts. Please wait before trying again.']);
    $this->postJson(route('portal.verification.send'))->assertTooManyRequests();
    Notification::assertSentToTimes($account, ResidentEmailVerificationNotification::class, 3);
    $this->post(route('portal.logout'));
    $this->post(route('portal.verification.send'))->assertRedirect(route('portal.login'));
});

test('existing accounts are classified for staff review without names or emails', function () {
    $selfService = User::factory()->resident()->create(['email' => 'self@example.test']);
    $selfService->resident->forceFill(['portal_registration_sent_at' => now()->subDays(3)])->save();
    $staffCode = User::factory()->resident()->create(['email' => 'staff-code@example.test']);
    DB::table('administrative_audits')->insert(['user_id' => $staffCode->id, 'actor' => $staffCode->name, 'action' => 'portal.account.registered', 'type' => 'auth', 'record' => null, 'created_at' => now(), 'updated_at' => now()]);
    $unknown = User::factory()->resident()->create(['email' => 'seeded@example.test']);

    expect([$selfService->portalActivationPath(), $staffCode->portalActivationPath(), $unknown->portalActivationPath()])
        ->toBe(['email_self_service', 'staff_code', 'unknown']);
    $this->actingAs(User::factory()->assignedOperations()->create())->getJson(route('staff.residents.show', $selfService->resident))
        ->assertJsonPath('portal_account.activation', 'email_self_service')->assertJsonPath('portal_account.email_verified', true);

    Artisan::call('portal:unverified-accounts');
    $output = Artisan::output();
    expect($output)->toContain('email self-service: 1', 'not recorded: 1', $selfService->resident->resident_number, $unknown->resident->resident_number)
        ->not->toContain($staffCode->resident->resident_number)
        ->not->toContain('self@example.test', 'seeded@example.test', $selfService->name, $unknown->name);
    expect($selfService->fresh()->is_active)->toBeTrue();
});

test('the record is confirmed to the resident only after their staff-issued code matches it', function () {
    $resident = staffCodeResident('WXYZ2345');

    $this->get(route('portal.register', ['step' => 'activation']))->assertOk()->assertViewHas('stage', 'code')
        ->assertDontSee($resident->full_name)->assertDontSee($resident->resident_number);

    $this->post(route('portal.register.verify'), activationAttempt($resident, 'WRONG999'))->assertRedirect(route('portal.registration.denied'));
    $this->get(route('portal.register'))->assertViewHas('stage', 'eligibility')->assertViewHas('verifiedResident', null)
        ->assertDontSee($resident->full_name)->assertDontSee($resident->resident_number);

    $this->post(route('portal.register.verify'), activationAttempt($resident, 'WXYZ2345'))->assertRedirect(route('portal.register'));
    $this->get(route('portal.register'))->assertViewHas('stage', 'account')
        ->assertSeeInOrder(['Verified resident record', $resident->full_name, $resident->resident_number]);
    $this->assertDatabaseEmpty('users');
});

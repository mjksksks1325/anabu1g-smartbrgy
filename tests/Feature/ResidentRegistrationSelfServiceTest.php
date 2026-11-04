<?php

use App\Http\Controllers\ResidentRegistrationController;
use App\Models\Resident;
use App\Models\User;
use App\Notifications\ResidentEmailVerificationNotification;
use App\Notifications\ResidentPortalActivationNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

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
    $response = $this->withCookie('portal_locale', $locale)->get(route('portal.register'))->assertOk()->assertViewHas('stage', 'code');
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
    $url = match ($case) {
        'unsigned' => route('portal.verification.verify', ['account' => $account->id, 'hash' => sha1('jane@example.test')]),
        'other email' => ResidentEmailVerificationNotification::verificationUrl($account->replicate()->forceFill(['id' => $account->id, 'email' => 'old@example.test'])),
        'expired' => ResidentEmailVerificationNotification::verificationUrl($account),
        'staff account' => ResidentEmailVerificationNotification::verificationUrl(User::factory()->assignedOperations()->create(['email_verified_at' => null])),
    };
    if ($case === 'expired') {
        $this->travel(61)->minutes();
    }

    $this->get($url)->assertForbidden();
    expect($account->fresh()->email_verified_at)->toBeNull();
})->with(['unsigned', 'other email', 'expired', 'staff account']);

test('a signed-in resident can ask for a new confirmation link, within a limit', function () {
    Notification::fake();
    $account = User::factory()->resident()->create(['email_verified_at' => null]);

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $this->actingAs($account, 'resident')->post(route('portal.verification.send'))->assertRedirect();
    }
    $this->post(route('portal.verification.send'))->assertTooManyRequests();
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

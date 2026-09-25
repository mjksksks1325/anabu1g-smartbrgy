<?php

use App\Models\Resident;
use App\Models\User;
use App\Notifications\ResidentPortalActivationNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

function selfServiceResident(array $attributes = []): Resident
{
    return Resident::factory()->create([
        'first_name' => 'Jane', 'middle_name' => 'A', 'last_name' => 'Santos',
        'date_of_birth' => '1990-02-03', 'contact_number' => '0917-123-4567',
        ...$attributes,
    ]);
}

function confirmSelfServiceRecord(TestCase $test): void
{
    $test->post(route('portal.register.name'), ['full_name' => '  jAnE   A   SANTOS  '])->assertRedirect(route('portal.register'));
    $test->post(route('portal.register.confirm-record'), [
        'date_of_birth' => '1990-02-03', 'contact_last_four' => '4567',
    ])->assertRedirect(route('portal.register'));
}

test('existing record reaches email step after normalized name and official-detail confirmation', function () {
    $resident = selfServiceResident();
    config()->set('mail.default', 'smtp');

    $this->get(route('portal.register'))->assertOk()->assertSee('Full name')->assertDontSee('name="email"', false);
    $this->post(route('portal.register.name'), ['full_name' => '  jAnE   A   SANTOS  '])->assertRedirect(route('portal.register'));
    $this->get(route('portal.register'))->assertSee('A possible record was found')->assertDontSee($resident->resident_number);
    $this->post(route('portal.register.confirm-record'), [
        'date_of_birth' => '1990-02-03', 'contact_last_four' => '4567',
    ])->assertRedirect(route('portal.register'));
    $this->get(route('portal.register'))->assertSee('Resident record verified')->assertSee('Send Activation Code');
    $this->assertDatabaseCount('residents', 1);
    $this->assertDatabaseEmpty('users');
});

test('unavailable email delivery offers staff activation without an unusable send button', function () {
    selfServiceResident();
    config()->set('mail.default', 'log');
    confirmSelfServiceRecord($this);

    $this->get(route('portal.register'))->assertOk()
        ->assertSee('Email activation codes are unavailable right now')
        ->assertSee('Verify Activation Code')
        ->assertDontSee('Send Activation Code')
        ->assertDontSee('name="email"', false);
});

test('Unicode name case variants still match the official record', function () {
    selfServiceResident(['first_name' => 'José']);
    $this->post(route('portal.register.name'), ['full_name' => 'JOSÉ A SANTOS'])
        ->assertRedirect(route('portal.register'));
    $this->get(route('portal.register'))->assertSee('A possible record was found');
});

test('eligible resident receives a bound activation email and creates an account linked to the existing record', function () {
    $resident = selfServiceResident();
    Notification::fake();
    config()->set('mail.default', 'smtp');
    confirmSelfServiceRecord($this);

    $this->post(route('portal.register.send-code'), ['email' => '  JANE@example.test  '])
        ->assertRedirect(route('portal.register'));
    $code = null;
    Notification::assertSentOnDemand(ResidentPortalActivationNotification::class, function ($notification, $channels, AnonymousNotifiable $notifiable) use ($resident, &$code): bool {
        $code = $notification->activationCode;

        $lines = $notification->toMail($notifiable)->introLines;

        return $notifiable->routeNotificationFor('mail') === 'jane@example.test'
            && $notification->residentNumber === $resident->resident_number
            && in_array('Resident Number: '.$resident->resident_number, $lines, true)
            && in_array('Activation Code: '.$notification->activationCode, $lines, true)
            && in_array('mail', $channels, true);
    });
    expect($code)->toBeString()->toHaveLength(32);
    expect($resident->fresh()->portal_registration_hash)->toBe(hash('sha256', $code));
    expect($resident->fresh()->portal_registration_email)->toBe('jane@example.test');
    $this->assertDatabaseMissing('residents', ['id' => $resident->id, 'portal_registration_email' => 'jane@example.test']);
    $this->get(route('portal.register'))->assertSee('Check your email')->assertDontSee($resident->resident_number)->assertDontSee($code);

    $this->post(route('portal.register.verify'), ['resident_number' => $resident->resident_number, 'activation_code' => $code])
        ->assertRedirect(route('portal.register'));
    $this->get(route('portal.register'))->assertSee('Step 2')->assertSee('jane@example.test');
    $this->post(route('portal.register.store'), [
        'email' => 'jane@example.test', 'password' => 'ResidentPassword123!',
        'password_confirmation' => 'ResidentPassword123!',
    ])->assertRedirect(route('portal.login'));

    $this->assertDatabaseCount('residents', 1);
    $this->assertDatabaseHas('users', ['resident_id' => $resident->id, 'email' => 'jane@example.test', 'role' => 'resident']);
    expect($resident->fresh()->portal_registration_email)->toBeNull();
});

test('a resident can restart registration from the code screen without ending a staff session', function () {
    $resident = selfServiceResident();
    $staff = User::factory()->create(['role' => 'staff']);
    $this->actingAs($staff);
    Notification::fake();
    config()->set('mail.default', 'smtp');
    confirmSelfServiceRecord($this);
    $this->post(route('portal.register.send-code'), ['email' => 'jane@example.test'])
        ->assertRedirect(route('portal.register'));
    $this->get(route('portal.register'))->assertSee('Check your email')->assertSee('Start over / Change email');
    $this->post(route('portal.register.reset'))->assertRedirect(route('portal.register'));
    $this->get(route('portal.register'))->assertSee('Full name')->assertDontSee('Check your email')
        ->assertSessionMissing('resident_code_sent')
        ->assertSessionMissing('resident_identity')
        ->assertSessionMissing('resident_verification');
    $this->assertAuthenticatedAs($staff);
    expect($resident->fresh()->portal_registration_hash)->toBeNull()
        ->and($resident->fresh()->portal_registration_email)->toBeNull()
        ->and($resident->fresh()->portal_registration_sent_at)->toBeNull();

    confirmSelfServiceRecord($this);
    $this->post(route('portal.register.send-code'), ['email' => 'jane+new@example.test'])
        ->assertRedirect(route('portal.register'));
    expect($resident->fresh()->portal_registration_hash)->toBeString();
    Notification::assertSentOnDemandTimes(ResidentPortalActivationNotification::class, 2);
});

test('refreshing registration clears the old step without revoking an emailed activation code', function () {
    $resident = selfServiceResident();
    $staff = User::factory()->create(['role' => 'staff']);
    $this->actingAs($staff);
    Notification::fake();
    config()->set('mail.default', 'smtp');
    confirmSelfServiceRecord($this);
    $this->post(route('portal.register.send-code'), ['email' => 'jane@example.test'])
        ->assertRedirect(route('portal.register'));
    $code = null;
    Notification::assertSentOnDemand(ResidentPortalActivationNotification::class, function ($notification) use (&$code): bool {
        $code = $notification->activationCode;

        return true;
    });

    $this->get(route('portal.register'))->assertSee('Check your email');
    $this->get(route('portal.register'))->assertSee('Full name')->assertDontSee('Check your email')
        ->assertSessionMissing('resident_name_check')
        ->assertSessionMissing('resident_identity')
        ->assertSessionMissing('resident_code_sent')
        ->assertSessionMissing('resident_verification')
        ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-store, private');
    $this->assertAuthenticatedAs($staff);
    expect($resident->fresh()->portal_registration_hash)->toBe(hash('sha256', $code));

    $this->post(route('portal.register.verify'), ['resident_number' => $resident->resident_number, 'activation_code' => $code])
        ->assertRedirect(route('portal.register'));
    $this->get(route('portal.register'))->assertSee('Step 2');
});

test('refreshing the account creation step removes the pending registration proof', function () {
    $resident = selfServiceResident();
    $code = 'staff-issued-code';
    $resident->forceFill([
        'portal_registration_hash' => hash('sha256', $code),
        'portal_registration_expires_at' => now()->addHour(),
    ])->save();
    $this->post(route('portal.register.verify'), ['resident_number' => $resident->resident_number, 'activation_code' => $code])
        ->assertRedirect(route('portal.register'));

    $this->get(route('portal.register'))->assertSee('Step 2');
    $this->get(route('portal.register'))->assertSee('Full name')->assertDontSee('Step 2')
        ->assertSessionMissing('resident_verification');
    $this->post(route('portal.register.store'), [
        'email' => 'jane@example.test', 'password' => 'ResidentPassword123!',
        'password_confirmation' => 'ResidentPassword123!',
    ])->assertRedirect(route('portal.registration.denied'));
    $this->assertDatabaseEmpty('users');
});

test('registration validation errors show the current step once before a refresh clears it', function () {
    selfServiceResident();
    config()->set('mail.default', 'smtp');
    confirmSelfServiceRecord($this);

    $this->post(route('portal.register.send-code'), ['email' => 'not-an-email'])
        ->assertSessionHasErrors('email');
    $this->get(route('portal.register'))->assertSee('Send Activation Code');
    $this->get(route('portal.register'))->assertSee('Full name')->assertDontSee('Send Activation Code')
        ->assertSessionMissing('resident_identity');
});

test('starting over does not revoke a newer activation code from another session', function () {
    $resident = selfServiceResident();
    $newerHash = hash('sha256', 'newer-code');
    $resident->portal_registration_hash = $newerHash;
    $resident->portal_registration_expires_at = now()->addHours(2);
    $resident->save();

    $this->withSession(['resident_code_sent' => [
        'resident_id' => $resident->id,
        'hash' => hash('sha256', 'older-code'),
        'sent_at' => now()->timestamp,
    ]])->post(route('portal.register.reset'))->assertRedirect(route('portal.register'));

    expect($resident->fresh()->portal_registration_hash)->toBe($newerHash);
});

test('unknown names reach assistance without creating residents or exposing record details', function () {
    $resident = selfServiceResident();
    $this->post(route('portal.register.name'), ['full_name' => 'Unknown Citizen'])
        ->assertRedirect(route('portal.registration.denied'));
    $this->get(route('portal.registration.denied'))
        ->assertSee('recently moved')->assertSee('valid ID')->assertDontSee($resident->full_name)
        ->assertDontSee($resident->resident_number)->assertDontSee($resident->contact_number);
    $this->post(route('portal.register.send-code'), ['email' => 'unknown@example.test'])
        ->assertRedirect(route('portal.registration.denied'));
    $this->assertDatabaseCount('residents', 1);
    $this->assertDatabaseEmpty('users');
});

test('public name checks are rate limited before records can be enumerated', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('portal.register.name'), ['full_name' => 'Unknown Citizen'])
            ->assertRedirect(route('portal.registration.denied'));
    }
    $this->post(route('portal.register.name'), ['full_name' => 'Unknown Citizen'])->assertTooManyRequests();
});

test('an expired name confirmation cannot advance to email', function () {
    selfServiceResident();
    $this->post(route('portal.register.name'), ['full_name' => 'Jane A Santos'])->assertRedirect(route('portal.register'));
    $this->travel(11)->minutes();
    $this->post(route('portal.register.confirm-record'), ['date_of_birth' => '1990-02-03', 'contact_last_four' => '4567'])
        ->assertRedirect(route('portal.register'));
    $this->get(route('portal.register'))->assertSee('Full name')->assertDontSee('Send Activation Code');
});

test('inactive and archived residents cannot pass the public name check', function (string $condition) {
    $resident = selfServiceResident();
    if ($condition === 'inactive') {
        $resident->update(['status' => 'inactive']);
    } else {
        $resident->delete();
    }

    $this->post(route('portal.register.name'), ['full_name' => 'Jane A Santos'])
        ->assertRedirect(route('portal.registration.denied'));
    $this->assertDatabaseEmpty('users');
})->with(['inactive', 'archived']);

test('wrong official details and identical records cannot select a resident arbitrarily', function (string $condition) {
    selfServiceResident();
    if ($condition === 'ambiguous') {
        selfServiceResident();
    }
    $this->post(route('portal.register.name'), ['full_name' => 'Jane A Santos'])->assertRedirect(route('portal.register'));
    $this->post(route('portal.register.confirm-record'), [
        'date_of_birth' => '1990-02-03', 'contact_last_four' => $condition === 'wrong' ? '0000' : '4567',
    ])->assertRedirect(route('portal.registration.denied'));
    $this->post(route('portal.register.send-code'), ['email' => 'jane@example.test'])
        ->assertRedirect(route('portal.registration.denied'));
    $this->assertDatabaseEmpty('users');
})->with(['wrong', 'ambiguous']);

test('same-name residents can be distinguished without disclosing matching records', function () {
    $resident = selfServiceResident();
    selfServiceResident(['date_of_birth' => '1981-01-01', 'contact_number' => '0999-888-1111']);
    confirmSelfServiceRecord($this);

    $this->get(route('portal.register'))->assertSee('Resident record verified')
        ->assertDontSee($resident->resident_number)->assertDontSee($resident->contact_number);
});

test('records without a usable contact number are directed to barangay assistance', function () {
    selfServiceResident(['contact_number' => null]);
    $this->post(route('portal.register.name'), ['full_name' => 'Jane A Santos'])->assertRedirect(route('portal.register'));
    $this->post(route('portal.register.confirm-record'), ['date_of_birth' => '1990-02-03', 'contact_last_four' => '4567'])
        ->assertRedirect(route('portal.registration.denied'));
    $this->get(route('portal.registration.denied'))->assertSee('Barangay Anabu I-G Hall');
});

test('already linked residents and duplicate email addresses cannot receive a self-service code', function (string $condition) {
    $resident = selfServiceResident();
    if ($condition === 'account') {
        User::factory()->resident()->create(['resident_id' => $resident->id]);
        $this->post(route('portal.register.name'), ['full_name' => 'Jane A Santos'])->assertRedirect(route('portal.register'));
        $this->post(route('portal.register.confirm-record'), ['date_of_birth' => '1990-02-03', 'contact_last_four' => '4567'])
            ->assertRedirect(route('portal.registration.denied'));
    } else {
        User::factory()->create(['email' => 'JANE@example.test']);
        Notification::fake();
        config()->set('mail.default', 'smtp');
        confirmSelfServiceRecord($this);
        $this->post(route('portal.register.send-code'), ['email' => 'jane@example.test'])
            ->assertSessionHasErrors('email');
        Notification::assertNothingSent();
    }
    expect($resident->fresh()->portal_registration_hash)->toBeNull();
})->with(['account', 'email']);

test('email issuance rechecks the resident if an account was linked after confirmation', function () {
    $resident = selfServiceResident();
    Notification::fake();
    config()->set('mail.default', 'smtp');
    confirmSelfServiceRecord($this);
    User::factory()->resident()->create(['resident_id' => $resident->id]);

    $this->post(route('portal.register.send-code'), ['email' => 'jane@example.test'])
        ->assertRedirect(route('portal.registration.denied'));
    Notification::assertNothingSent();
    expect($resident->fresh()->portal_registration_hash)->toBeNull();
});

test('the log mail transport never writes an activation code to application logs', function () {
    $resident = selfServiceResident();
    confirmSelfServiceRecord($this);
    $this->post(route('portal.register.send-code'), ['email' => 'jane@example.test'])
        ->assertSessionHasErrors('email');
    expect($resident->fresh()->portal_registration_hash)->toBeNull();
});

test('SMTP failure shows a safe error and preserves an existing activation code', function () {
    $resident = selfServiceResident();
    $previousHash = hash('sha256', 'previous-staff-code');
    $resident->portal_registration_hash = $previousHash;
    $resident->portal_registration_expires_at = now()->addHours(2);
    $resident->save();
    config()->set('mail.default', 'smtp');
    confirmSelfServiceRecord($this);
    Notification::shouldReceive('send')->once()
        ->andThrow(new TransportException('535 Username and Password not accepted'));

    $this->from(route('portal.register'))
        ->post(route('portal.register.send-code'), ['email' => 'jane@example.test'])
        ->assertRedirect(route('portal.register'))
        ->assertSessionHasErrors(['email' => 'We could not send the activation email. Please try again later or request an activation code from barangay staff.']);

    expect($resident->fresh()->portal_registration_hash)->toBe($previousHash)
        ->and($resident->fresh()->portal_registration_email)->toBeNull()
        ->and($resident->fresh()->portal_registration_sent_at)->toBeNull();
});

test('publicly issued codes remain bound to the receiving email and expire', function () {
    $resident = selfServiceResident();
    Notification::fake();
    config()->set('mail.default', 'smtp');
    confirmSelfServiceRecord($this);
    $this->post(route('portal.register.send-code'), ['email' => 'jane@example.test'])->assertRedirect(route('portal.register'));
    $code = null;
    Notification::assertSentOnDemand(ResidentPortalActivationNotification::class, function ($notification) use (&$code): bool {
        $code = $notification->activationCode;

        return true;
    });
    $this->post(route('portal.register.verify'), ['resident_number' => $resident->resident_number, 'activation_code' => 'wrong-code'])
        ->assertRedirect(route('portal.registration.denied'));
    $this->post(route('portal.register.verify'), ['resident_number' => $resident->resident_number, 'activation_code' => $code])
        ->assertRedirect(route('portal.register'));
    $this->post(route('portal.register.store'), [
        'email' => 'other@example.test', 'password' => 'ResidentPassword123!',
        'password_confirmation' => 'ResidentPassword123!',
    ])->assertSessionHasErrors('email');
    $this->assertDatabaseEmpty('users');

    $resident->forceFill(['portal_registration_expires_at' => now()->subMinute()])->save();
    $this->post(route('portal.register.store'), [
        'email' => 'jane@example.test', 'password' => 'ResidentPassword123!',
        'password_confirmation' => 'ResidentPassword123!',
    ])->assertRedirect(route('portal.registration.denied'));
    $this->assertDatabaseEmpty('users');
});

test('self-service requests are throttled and cannot immediately rotate a code', function () {
    $resident = selfServiceResident();
    Notification::fake();
    config()->set('mail.default', 'smtp');
    confirmSelfServiceRecord($this);
    $identityProof = session('resident_identity');
    $this->post(route('portal.register.send-code'), ['email' => 'jane@example.test'])->assertRedirect(route('portal.register'));
    $hash = $resident->fresh()->portal_registration_hash;
    $this->withSession(['resident_identity' => $identityProof])->post(route('portal.register.send-code'), ['email' => 'jane@example.test'])
        ->assertRedirect(route('portal.registration.denied'));
    expect($resident->fresh()->portal_registration_hash)->toBe($hash);
    Notification::assertSentOnDemandOnce(ResidentPortalActivationNotification::class);
});

test('a later self-service request rotates the code and invalidates the previous one', function () {
    $resident = selfServiceResident();
    Notification::fake();
    config()->set('mail.default', 'smtp');
    confirmSelfServiceRecord($this);
    $this->post(route('portal.register.send-code'), ['email' => 'jane@example.test'])->assertRedirect(route('portal.register'));
    $oldHash = $resident->fresh()->portal_registration_hash;

    $this->travel(61)->seconds();
    confirmSelfServiceRecord($this);
    $this->post(route('portal.register.send-code'), ['email' => 'jane@example.test'])->assertRedirect(route('portal.register'));

    expect($resident->fresh()->portal_registration_hash)->not->toBe($oldHash);
    Notification::assertSentOnDemandTimes(ResidentPortalActivationNotification::class, 2);
});

test('staff-issued activation replaces an emailed code without retaining its email binding', function () {
    $resident = selfServiceResident();
    Notification::fake();
    config()->set('mail.default', 'smtp');
    confirmSelfServiceRecord($this);
    $this->post(route('portal.register.send-code'), ['email' => 'jane@example.test'])->assertRedirect(route('portal.register'));
    $oldHash = $resident->fresh()->portal_registration_hash;

    $staff = User::factory()->create(['role' => 'staff']);
    $this->actingAs($staff)->postJson(route('admin.residents.portal-activation', $resident))->assertOk();

    expect($resident->fresh()->portal_registration_hash)->not->toBe($oldHash)
        ->and($resident->fresh()->portal_registration_email)->toBeNull()
        ->and($resident->fresh()->portal_registration_sent_at)->toBeNull();
});

<?php

use App\Http\Responses\PasswordResetLinkRequestResponse;
use App\MailDelivery;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Features;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());
});

test('reset password link screen can be rendered', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'Staff-Password-2026.',
            'password_confirmation' => 'Staff-Password-2026.',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login', absolute: false));

        return true;
    });
});

test('resident portal links open a portal styled reset request while staff keep the original screen', function () {
    $this->get(route('password.request', ['from' => 'portal']))
        ->assertOk()
        ->assertViewIs('portal.forgot-password')
        ->assertSee('action="'.route('password.email').'"', false)
        ->assertSee('href="'.route('portal.login').'"', false);

    $this->get(route('password.request'))
        ->assertOk()
        ->assertViewIs('livewire.auth.forgot-password')
        ->assertSee('href="'.route('portal.login').'"', false);
});

test('a resident reset request from the portal sends the same reset link and returns to the portal screen', function () {
    Notification::fake();
    $resident = User::factory()->resident()->create();

    $this->from(route('password.request', ['from' => 'portal']))
        ->post(route('password.email'), ['email' => $resident->email])
        ->assertRedirect(route('password.request', ['from' => 'portal']))
        ->assertSessionHas('status');

    Notification::assertSentTo($resident, ResetPassword::class);
});

test('the reset password screen offers residents the portal log in', function () {
    $this->get(route('password.reset', 'example-token'))
        ->assertOk()
        ->assertSee('href="'.route('portal.login').'"', false);
});

test('a reset request gets the same answer whether or not the email has an account', function () {
    Notification::fake();
    $resident = User::factory()->resident()->create();
    $neutral = 'If an account uses this email address, we will send it a link to reset the password. The link works for 60 minutes. Check your Spam folder if it does not arrive.';
    $portal = route('password.request', ['from' => 'portal']);

    foreach ([$resident->email, 'nobody@example.test', $resident->email] as $email) {
        $this->from($portal)->post(route('password.email'), ['email' => $email])
            ->assertRedirect($portal)->assertSessionHasNoErrors()->assertSessionHas('status', $neutral);
    }
    $this->postJson(route('password.email'), ['email' => 'nobody@example.test'])->assertOk()->assertExactJson(['message' => $neutral]);

    Notification::assertSentToTimes($resident, ResetPassword::class, 1);
    $this->from($portal)->post(route('password.email'), ['email' => 'not-an-email'])->assertSessionHasErrors('email');
});

test('a mail server failure during a reset request is reported and answered like any other request', function () {
    Exceptions::fake();
    useFailingMailer();
    $resident = User::factory()->resident()->create();
    $portal = route('password.request', ['from' => 'portal']);

    $this->from($portal)->post(route('password.email'), ['email' => $resident->email])
        ->assertRedirect($portal)->assertSessionHasNoErrors()->assertSessionHas('status');
    Exceptions::assertReported(TransportException::class);
});

test('resident reset links open the portal reset page and return to the portal log in', function () {
    Notification::fake();
    $resident = User::factory()->resident()->create(['email' => 'jane@example.test']);
    $this->post(route('password.email'), ['email' => $resident->email]);

    Notification::assertSentTo($resident, ResetPassword::class, function (ResetPassword $notification) use ($resident) {
        $url = $notification->toMail($resident)->actionUrl;
        expect($url)->toContain('from=portal');

        $this->get($url)->assertOk()->assertViewIs('portal.reset-password')
            ->assertSee('action="'.route('password.update').'"', false)
            ->assertSee('value="'.$notification->token.'"', false)
            ->assertSee('value="jane@example.test"', false);

        $this->from($url)->post(route('password.update'), ['token' => $notification->token, 'email' => $resident->email, 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertRedirect($url)->assertSessionHasErrors(['password' => 'The password field must be at least 12 characters.']);
        $this->post(route('password.update'), ['token' => $notification->token, 'email' => $resident->email, 'password' => 'New-Resident-2026!', 'password_confirmation' => 'New-Resident-2026!'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('portal.login'))
            ->assertSessionHas('status', 'Your password has been reset. Log in with your new password.');

        return true;
    });
    expect(Hash::check('New-Resident-2026!', $resident->fresh()->password))->toBeTrue();
});

test('staff reset links keep the staff reset screen and staff log in', function () {
    Notification::fake();
    $staff = User::factory()->create(['role' => 'staff']);
    $this->post(route('password.email'), ['email' => $staff->email]);

    Notification::assertSentTo($staff, ResetPassword::class, function (ResetPassword $notification) use ($staff) {
        $url = $notification->toMail($staff)->actionUrl;
        expect($url)->not->toContain('from=portal');
        $this->get($url)->assertOk()->assertViewIs('livewire.auth.reset-password');

        return true;
    });
});

test('a wrong token and an unknown email fail a reset with the same message', function () {
    $resident = User::factory()->resident()->create();
    $input = ['password' => 'New-Resident-2026!', 'password_confirmation' => 'New-Resident-2026!'];

    $this->post(route('password.update'), ['token' => 'wrong', 'email' => $resident->email, ...$input])
        ->assertSessionHasErrors(['email' => 'This password reset link is invalid or has expired. Request a new one.']);
    $this->post(route('password.update'), ['token' => 'wrong', 'email' => 'nobody@example.test', ...$input])
        ->assertSessionHasErrors(['email' => 'This password reset link is invalid or has expired. Request a new one.']);
    expect(Hash::check('New-Resident-2026!', $resident->fresh()->password))->toBeFalse();
});

test('a reset link stops working after 60 minutes', function () {
    $resident = User::factory()->resident()->create();
    $token = Password::broker()->createToken($resident);
    $this->travel(61)->minutes();

    $this->post(route('password.update'), ['token' => $token, 'email' => $resident->email, 'password' => 'New-Resident-2026!', 'password_confirmation' => 'New-Resident-2026!'])
        ->assertSessionHasErrors('email');
    expect(Hash::check('New-Resident-2026!', $resident->fresh()->password))->toBeFalse();
});

test('a server that does not deliver email sends no reset link, keeps the reply neutral and records the failure', function (string $mailer) {
    Notification::fake();
    config(['mail.default' => $mailer]);
    $resident = User::factory()->resident()->create();
    $staff = User::factory()->create(['role' => 'staff']);
    $portal = route('password.request', ['from' => 'portal']);

    $known = $this->from($portal)->post(route('password.email'), ['email' => $resident->email]);
    $unknown = $this->from($portal)->post(route('password.email'), ['email' => 'nobody@example.test']);
    $this->from(route('password.request'))->post(route('password.email'), ['email' => $staff->email]);

    $known->assertRedirect($portal)->assertSessionHas('status', PasswordResetLinkRequestResponse::MESSAGE);
    $unknown->assertRedirect($portal)->assertSessionHas('status', PasswordResetLinkRequestResponse::MESSAGE);
    Notification::assertNothingSent();
    $this->assertDatabaseHas('administrative_audits', ['action' => 'auth.password-reset-mail-unavailable', 'type' => 'security', 'record' => 'resident_portal:'.$resident->id, 'user_id' => null]);
    $this->assertDatabaseHas('administrative_audits', ['action' => 'auth.password-reset-mail-unavailable', 'record' => (string) $staff->id]);
    $this->assertDatabaseCount('administrative_audits', 2);
})->with(['log']);

test('a mail server failure during a reset request is recorded for administrators without the address or token', function () {
    Exceptions::fake();
    useFailingMailer();
    $staff = User::factory()->create(['role' => 'staff']);

    $this->from(route('password.request'))->post(route('password.email'), ['email' => $staff->email])
        ->assertRedirect(route('password.request'))->assertSessionHas('status', PasswordResetLinkRequestResponse::MESSAGE);

    $audit = DB::table('administrative_audits')->where('action', 'auth.password-reset-mail-failed')->sole();
    expect($audit->record)->toBe((string) $staff->id)->and($audit->type)->toBe('security')
        ->and(json_encode($audit))->not->toContain($staff->email)->not->toContain('token');
});

test('MailDelivery accepts only mailers that send', function () {
    config(['mail.default' => 'smtp']);
    expect(MailDelivery::configured())->toBeTrue();
    config(['mail.default' => 'log']);
    expect(MailDelivery::configured())->toBeFalse()->and(MailDelivery::problems()[0])->toContain('MAIL_MAILER=log');
    config(['mail.default' => 'smtp', 'mail.from.address' => 'hello@example.com']);
    expect(MailDelivery::problems())->toHaveCount(1)->and(MailDelivery::problems()[0])->toContain('placeholder');
    config(['mail.from.address' => 'records@anabu1g.gov.ph']);
    expect(MailDelivery::problems())->toBe([]);
});

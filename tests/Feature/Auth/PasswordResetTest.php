<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

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
            'password' => 'password',
            'password_confirmation' => 'password',
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

<?php

use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
});

test('two factor challenge redirects to login when not authenticated', function () {
    $response = $this->get(route('two-factor.login'));

    $response->assertRedirect(route('login'));
});

test('two factor challenge can be rendered', function () {
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('two-factor.login'));
});

test('a completed two factor login records one successful sign in', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('two-factor.login'));
    $this->assertDatabaseEmpty('administrative_audits');

    $this->post(route('two-factor.login.store'), [
        'recovery_code' => 'recovery-code-1',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseCount('administrative_audits', 1);
    $this->assertDatabaseHas('administrative_audits', [
        'user_id' => $user->id,
        'action' => 'auth.login',
        'type' => 'auth',
    ]);
});

<?php

use App\Models\User;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk()
        ->assertSee('Barangay Operations Portal')
        ->assertSee('Staff email address')
        ->assertSee('Sign in to staff portal')
        ->assertSee('Contact barangay office')
        ->assertSee('css/figma-auth.css');
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->superAdmin()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticated();
    $this->assertDatabaseHas('administrative_audits', [
        'user_id' => $user->id,
        'actor' => $user->name,
        'action' => 'auth.login',
        'type' => 'auth',
        'record' => null,
    ]);

    $this->getJson(route('admin.audit.index'))
        ->assertJsonCount(1, 'events')
        ->assertJsonPath('events.0.action', 'Login')
        ->assertJsonPath('events.0.detail', 'Successful sign in')
        ->assertJsonPath('events.0.user', $user->name)
        ->assertJsonPath('events.0.type', 'auth');
    $this->getJson(route('admin.audit.index'))->assertJsonCount(1, 'events');
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrorsIn('email');

    $this->assertGuest();
    $this->assertDatabaseEmpty('administrative_audits');
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
    $this->assertDatabaseEmpty('administrative_audits');
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('login'));

    $this->assertGuest();
});

test('users can logout through the confirmation dialog request', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('logout'))->assertNoContent();

    $this->assertGuest();
});

test('personnel welcome greeting is consumed once after successful login', function () {
    $user = User::factory()->superAdmin()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHas('personnel_login_greeting', true);
    $this->get(route('admin.dashboard'))->assertOk()
        ->assertSee('window.PERSONNEL_LOGIN_GREETING = true;', false)
        ->assertSessionMissing('personnel_login_greeting');

    foreach (['staff.residents.index', 'staff.voters', 'staff.document-requests.index',
        'staff.request-eligibility', 'staff.incidents.index', 'admin.dashboard'] as $route) {
        $this->get(route($route))->assertOk()
            ->assertSee('window.PERSONNEL_LOGIN_GREETING = false;', false);
    }
});

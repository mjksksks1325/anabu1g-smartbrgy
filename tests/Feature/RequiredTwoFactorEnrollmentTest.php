<?php

use App\Http\Middleware\RequirePrivilegedTwoFactor;
use App\Livewire\Settings\Security;
use App\Models\User;
use App\StaffPermissions;
use Laravel\Fortify\Features;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    config(['access.privileged_two_factor' => true]);
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
});

test('a remembered password timestamp cannot skip the explicit password step before enrollment', function () {
    $user = User::factory()->superAdmin()->create(['password' => 'Own-Password-2026!']);
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

    $this->get(route('security.edit'))->assertRedirect(route('password.confirm'));
    $this->getJson(route('two-factor.qr-code'))->assertStatus(423)->assertJsonPath('password_confirmation', true);
    $this->postJson(route('two-factor.enable'))->assertStatus(423);
    $this->postJson(route('password.confirm.store'), ['password' => 'wrong-password'])->assertUnprocessable()
        ->assertSessionMissing(RequirePrivilegedTwoFactor::PASSWORD_CONFIRMATION_KEY);
    expect($user->fresh()->two_factor_secret)->toBeNull();

    $this->post(route('password.confirm.store'), ['password' => 'Own-Password-2026!'])->assertRedirect(route('security.edit'));
    $this->get(route('security.edit'))->assertOk()->assertSee('data-two-factor-enrollment-shell', false);
    expect($user->fresh()->two_factor_secret)->not->toBeNull();
});

test('an expired enrollment confirmation or one for another account cannot open the QR setup', function (bool $expired) {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user)->withSession([
        'auth.password_confirmed_at' => time(),
        RequirePrivilegedTwoFactor::PASSWORD_CONFIRMATION_KEY => [
            'user_id' => $expired ? $user->id : $user->id + 1,
            'confirmed_at' => $expired ? now()->subSeconds((int) config('auth.password_timeout', 10800) + 1)->timestamp : now()->timestamp,
        ],
    ]);

    $this->get(route('security.edit'))->assertRedirect(route('password.confirm'));
    expect($user->fresh()->two_factor_secret)->toBeNull();
})->with([true, false]);

test('required enrollment renders setup in the sign-in card and never the staff workspace', function (array $attributes) {
    $user = User::factory()->create($attributes);

    $this->actingAs($user)->postJson(route('password.confirm.store'), ['password' => 'password'])->assertCreated();
    $this->get(route('security.edit'))->assertOk()
        ->assertSee('data-two-factor-enrollment-shell', false)
        ->assertSee('class="staff-auth-gov"', false)
        ->assertSee('class="civic-auth-aside"', false)
        ->assertSee('Barangay Operations Portal')
        ->assertDontSee('data-modal="two-factor-setup-modal"', false)
        ->assertDontSee('Staff navigation')
        ->assertDontSee('Update password')
        ->assertDontSee('Resident Records')
        ->assertDontSee('Incident Reports')
        ->assertDontSee('wire:click="disable"', false);

    expect($user->fresh()->two_factor_secret)->not->toBeNull();
    expect($user->fresh()->two_factor_confirmed_at)->toBeNull();
    $this->getJson(route('staff.residents.index'))->assertForbidden();
})->with([
    'super admin' => [['role' => 'admin', 'is_super_admin' => true]],
    'captain' => [['role' => 'captain']],
    'secretary' => [['role' => 'staff', 'staff_permissions' => ['records.view', 'records.update', 'documents.view', 'documents.issue']]],
]);

test('required setup cannot be dismissed or skipped and a reload keeps the same QR secret', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

    Livewire::test(Security::class)->assertSet('enrollmentRequired', true)->assertSet('showModal', true)
        ->call('closeModal')->assertSet('showModal', true);
    $secret = $user->fresh()->two_factor_secret;
    Livewire::test(Security::class)->assertSet('showModal', true);
    expect($user->fresh()->two_factor_secret)->toBe($secret);
    Livewire::test(Security::class)->call('finishEnrollment')->assertForbidden();
    Livewire::test(Security::class)->call('updatePassword')->assertForbidden();
});

test('an invalid authenticator code keeps the setup open and workspace data blocked', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

    Livewire::test(Security::class)->call('showVerificationIfNecessary')->set('code', '000000')
        ->call('confirmTwoFactor')->assertHasErrors('code')->assertSet('showModal', true);

    expect($user->fresh()->hasConfirmedTwoFactor())->toBeFalse();
    $this->getJson(route('staff.residents.index'))->assertForbidden();
});

test('a valid authenticator code opens recovery codes before continuing to the permitted workspace', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
    $component = Livewire::test(Security::class);
    $secret = decrypt($user->fresh()->two_factor_secret);
    $code = (new Google2FA)->getCurrentOtp($secret);

    $component->call('showVerificationIfNecessary')->set('code', $code)->call('confirmTwoFactor')
        ->assertHasNoErrors()->assertSet('twoFactorEnabled', true)->assertSet('showModal', true)
        ->assertSee('2FA recovery codes')->assertSee('Continue to workspace')
        ->assertDontSee('Update password');

    expect($user->fresh()->hasConfirmedTwoFactor())->toBeTrue();
    $component->call('finishEnrollment')->assertRedirect(route(StaffPermissions::landing($user)));
    $this->getJson(route('staff.residents.index'))->assertOk();
    $this->get(route('security.edit'))->assertOk()->assertDontSee('data-two-factor-enrollment-shell', false)->assertSee('Update password');
});

test('optional enrollment retains normal security settings', function () {
    $user = User::factory()->create(['role' => 'staff', 'staff_permissions' => ['dashboard.view']]);

    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->get(route('security.edit'))
        ->assertOk()->assertDontSee('data-two-factor-enrollment-shell', false)->assertSee('Update password');
});

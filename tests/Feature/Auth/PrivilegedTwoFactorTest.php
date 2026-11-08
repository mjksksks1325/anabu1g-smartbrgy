<?php

use App\Http\Middleware\RequirePrivilegedTwoFactor;
use App\Livewire\Settings\Security;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => config(['access.privileged_two_factor' => true]));

/** A confirmed two-factor setup, as Fortify stores it after the authenticator code is accepted. */
function withConfirmedTwoFactor(User $user): User
{
    $user->forceFill(['two_factor_secret' => encrypt('TESTSECRET234567'), 'two_factor_recovery_codes' => encrypt(json_encode(['code-1'])), 'two_factor_confirmed_at' => now()])->save();

    return $user;
}

test('privileged personnel are decided by their current access, not their role name alone', function (array $attributes, bool $privileged) {
    expect(User::factory()->create($attributes)->requiresTwoFactorAuthentication())->toBe($privileged);
})->with([
    'super admin' => [['role' => 'admin', 'is_super_admin' => true], true],
    'barangay captain' => [['role' => 'captain'], true],
    'VAWC handler' => [['role' => 'staff', 'staff_permissions' => ['incidents.view', 'vawc.view']], true],
    'resident records editor' => [['role' => 'staff', 'staff_permissions' => ['records.view', 'records.update']], true],
    'certificate issuer' => [['role' => 'staff', 'staff_permissions' => ['documents.view', 'documents.issue']], true],
    'certificate revoker' => [['role' => 'staff', 'staff_permissions' => ['documents.view', 'documents.revoke']], true],
    'read-only clerk' => [['role' => 'staff', 'staff_permissions' => ['documents.view', 'records.view', 'voters.view']], false],
    'inactive administrator' => [['role' => 'admin', 'is_super_admin' => true, 'is_active' => false], false],
]);

test('until two-factor is confirmed, a privileged account reaches only the setup page, password confirmation and logout', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->get('/staff')->assertRedirect(route('security.edit'))->assertSessionHas('two_factor_required');
    $this->get(route('staff.residents.index'))->assertRedirect(route('security.edit'));
    $this->getJson(route('staff.residents.index'))->assertForbidden()->assertJsonPath('two_factor_required', true)
        ->assertJsonPath('message', RequirePrivilegedTwoFactor::MESSAGE);
    $this->getJson(route('staff.issued-certificates.index'))->assertForbidden();
    $this->postJson(route('staff.issued-certificates.store'), [])->assertForbidden()->assertJsonPath('two_factor_required', true);
    $this->get(route('admin.users.index'))->assertRedirect(route('security.edit'));

    $this->get(route('security.edit'))->assertRedirect(route('password.confirm'));
    $this->get(route('password.confirm'))->assertOk();
    $this->postJson(route('password.confirm.store'), ['password' => 'password'])->assertCreated();
    $this->get(route('security.edit'))->assertOk()
        ->assertSee('data-two-factor-required', false)->assertSeeText('Enable two-factor authentication');
    $this->postJson(route('staff.locale'), ['locale' => 'fil'])->assertOk();
    $this->post(route('logout'))->assertRedirect();
});

test('a generated but unconfirmed secret does not count as two-factor authentication', function () {
    $captain = User::factory()->create(['role' => 'captain']);
    $captain->forceFill(['two_factor_secret' => encrypt('TESTSECRET234567'), 'two_factor_confirmed_at' => null])->save();

    $this->actingAs($captain)->getJson(route('staff.residents.index'))->assertForbidden()->assertJsonPath('two_factor_required', true);
});

test('a confirmed privileged account works normally and cannot turn two-factor off', function () {
    $admin = withConfirmedTwoFactor(User::factory()->superAdmin()->create());

    $this->actingAs($admin)->getJson(route('staff.residents.index'))->assertOk();
    $this->withSession(['auth.password_confirmed_at' => time()])->deleteJson(route('two-factor.disable'))->assertForbidden();
    expect($admin->fresh()->hasConfirmedTwoFactor())->toBeTrue();

    $this->withSession(['auth.password_confirmed_at' => time()])->get(route('security.edit'))->assertOk()
        ->assertDontSee('wire:click="disable"', false)->assertSeeText('cannot be turned off');
    Livewire::actingAs($admin)->test(Security::class)->call('disable')->assertForbidden();
    expect($admin->fresh()->hasConfirmedTwoFactor())->toBeTrue();
});

test('staff without privileged access are not asked, and a new privileged permission applies on the next request', function () {
    $clerk = User::factory()->create(['role' => 'staff', 'staff_permissions' => ['records.view']]);

    $this->actingAs($clerk)->getJson(route('staff.residents.index'))->assertOk();

    $clerk->forceFill(['staff_permissions' => ['records.view', 'records.update']])->save();
    $this->actingAs($clerk->fresh())->getJson(route('staff.residents.index'))->assertForbidden()->assertJsonPath('two_factor_required', true);
});

test('Livewire requests other than the two-factor settings are refused until setup is complete', function () {
    $admin = User::factory()->superAdmin()->create();
    $payload = fn (string $name) => ['components' => [['snapshot' => json_encode(['data' => [], 'memo' => ['id' => 'x', 'name' => $name], 'checksum' => 'x']), 'updates' => [], 'calls' => []]]];
    $livewireUpdate = route('default-livewire.update');

    $this->actingAs($admin)->postJson($livewireUpdate, $payload('settings.profile'))->assertForbidden()->assertJsonPath('two_factor_required', true);
    $this->postJson($livewireUpdate, ['components' => [['snapshot' => json_encode(['memo' => ['name' => 'settings.security']])], ['snapshot' => json_encode(['memo' => ['name' => 'settings.profile']])]]])
        ->assertForbidden();
    $allowed = $this->postJson($livewireUpdate, $payload('settings.security'));
    expect($allowed->json('two_factor_required'))->toBeNull();
});

test('a privileged account signing in without two-factor is sent to set it up', function () {
    $admin = User::factory()->superAdmin()->create(['password' => 'Correct-Passw0rd!']);

    $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'Correct-Passw0rd!'])->assertRedirect();
    $this->followingRedirects()->get('/staff')->assertOk()->assertSee('name="password"', false);
    $this->assertAuthenticatedAs($admin);
});

test('outside production the rule can be turned off, and it is always on in production', function () {
    config(['access.privileged_two_factor' => false]);
    $this->actingAs(User::factory()->superAdmin()->create())->getJson(route('staff.residents.index'))->assertOk();

    $config = file_get_contents(config_path('access.php'));
    expect($config)->toContain("'privileged_two_factor' => env('APP_ENV') === 'production' || (bool) env('PRIVILEGED_TWO_FACTOR', true)");
});

<?php

use App\Livewire\Settings\Security;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;
use Livewire\Livewire;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);
});

test('security settings page can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'));

    $response->assertOk();

    $response->assertSee('Two-factor authentication');
    $response->assertSee('Enable 2FA');
});

test('security settings page requires password confirmation when enabled', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('security.edit'));

    $response->assertRedirect(route('password.confirm'));
});

test('security settings page renders without two factor when feature is disabled', function () {
    config(['fortify.features' => []]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertSee('Update password')
        ->assertDontSee('Manage your passkeys for passwordless sign-in')
        ->assertDontSee('Add a passkey to sign in without a password')
        ->assertDontSee('Two-factor authentication');
});

test('two factor authentication disabled when confirmation abandoned between requests', function () {
    $user = User::factory()->create();

    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => null,
    ])->save();

    $this->actingAs($user);

    $component = Livewire::test(Security::class);

    $component->assertSet('twoFactorEnabled', false);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
    ]);
});

test('password can be updated', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user);

    $response = Livewire::test(Security::class)
        ->set('current_password', 'password')
        ->set('password', 'New-Password-2026.')
        ->set('password_confirmation', 'New-Password-2026.')
        ->call('updatePassword');

    $response->assertHasNoErrors();

    expect(Hash::check('New-Password-2026.', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user);

    $response = Livewire::test(Security::class)
        ->set('current_password', 'wrong-password')
        ->set('password', 'New-Password-2026.')
        ->set('password_confirmation', 'New-Password-2026.')
        ->call('updatePassword');

    $response->assertHasErrors(['current_password']);
});

test('a staff password change in Settings follows the staff password policy', function (string $password, bool $accepted) {
    $user = User::factory()->create(['password' => Hash::make('password')]);
    $this->actingAs($user);

    $response = Livewire::test(Security::class)
        ->set('current_password', 'password')
        ->set('password', $password)
        ->set('password_confirmation', $password)
        ->call('updatePassword');

    $accepted ? $response->assertHasNoErrors() : $response->assertHasErrors(['password']);
    expect(Hash::check($password, $user->refresh()->password))->toBe($accepted);
})->with([
    'period as the only symbol' => ['Barangay.Staff2026', true],
    'twelve lowercase letters' => ['abcdefghijkl', false],
    'no symbol' => ['BarangayStaff2026', false],
]);

test('the Settings password form describes the staff password policy', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Security::class)->assertSee('At least 12 characters, with an uppercase and a lowercase letter, a number and a symbol (a period counts).');
});

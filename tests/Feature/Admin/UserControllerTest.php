<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('requires authentication and administrator access to account management', function () {
    $this->getJson(route('admin.users.index'))->assertUnauthorized();
    $this->actingAs(User::factory()->create(['role' => 'staff']))
        ->getJson(route('admin.users.index'))->assertForbidden();
});

it('creates a persisted account with a hashed password and audit entry', function () {
    $admin = User::factory()->superAdmin()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->postJson(route('admin.users.store'), [
        'name' => 'Records Clerk', 'email' => 'clerk@example.test',
        'password' => 'SecurePassword123!', 'role' => 'staff', 'is_active' => true,
    ])->assertCreated();

    $user = User::findOrFail($response->json('id'));
    expect($user->role)->toBe('staff');
    expect(Hash::check('SecurePassword123!', $user->password))->toBeTrue();
    $this->assertDatabaseHas('administrative_audits', ['user_id' => $admin->id, 'action' => 'admin.users.store']);
});

it('rejects missing fields invalid roles and duplicate emails', function () {
    $admin = User::factory()->superAdmin()->create(['role' => 'admin']);

    $this->actingAs($admin)->postJson(route('admin.users.store'), [
        'name' => '', 'email' => $admin->email, 'password' => 'short', 'role' => 'superuser',
    ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password', 'role', 'is_active']);
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('administrative_audits', 0);
});

it('prevents staff from changing accounts', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $target = User::factory()->create();

    $this->actingAs($staff)->patchJson(route('admin.users.update', $target), [
        'name' => 'Changed', 'email' => $target->email, 'role' => 'admin', 'is_active' => false,
    ])->assertForbidden();
    expect($target->fresh()->name)->toBe($target->name);
});

it('updates accounts without replacing a blank password', function () {
    $admin = User::factory()->superAdmin()->create(['role' => 'admin']);
    $target = User::factory()->create();
    $password = $target->password;

    $this->actingAs($admin)->patchJson(route('admin.users.update', $target), [
        'name' => 'Updated Clerk', 'email' => $target->email,
        'role' => 'staff', 'is_active' => false, 'password' => null,
    ])->assertOk();

    expect($target->fresh())->name->toBe('Updated Clerk')->is_active->toBeFalse()->password->toBe($password);
});

it('prevents administrators from removing their own access', function (array $changes) {
    $admin = User::factory()->superAdmin()->create(['role' => 'admin']);

    $this->actingAs($admin)->patchJson(route('admin.users.update', $admin), [
        'name' => $admin->name, 'email' => $admin->email, 'role' => 'admin', 'is_active' => true, ...$changes,
    ])->assertUnprocessable()->assertJsonValidationErrors('role');

    expect($admin->fresh())->role->toBe('admin')->is_active->toBeTrue();
})->with(['suspend self' => [['is_active' => false]], 'demote self' => [['role' => 'staff']]]);

it('does not expose authentication secrets in the account list', function () {
    $admin = User::factory()->superAdmin()->create(['role' => 'admin', 'two_factor_secret' => 'private-secret']);

    $this->actingAs($admin)->getJson(route('admin.users.index'))
        ->assertOk()->assertJsonPath('users.0.email', $admin->email)
        ->assertJsonMissingPath('users.0.password')->assertJsonMissingPath('users.0.two_factor_secret');
});

it('denies suspended accounts even when they already have a session', function () {
    $user = User::factory()->create(['is_active' => false]);

    $this->actingAs($user)->getJson(route('admin.dashboard.summary'))->assertUnauthorized();
    $this->assertGuest();
});

it('does not authenticate a suspended account with a correct password', function () {
    $user = User::factory()->create(['is_active' => false]);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
    $this->assertDatabaseEmpty('administrative_audits');
});

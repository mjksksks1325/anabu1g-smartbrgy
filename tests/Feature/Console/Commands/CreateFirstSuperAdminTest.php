<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates the first super admin without putting the password in audit history', function () {
    $password = 'A-strong-password-123';

    $this->artisan('user:create-first-super-admin')
        ->expectsQuestion('Full name', 'Barangay Administrator')
        ->expectsQuestion('Email address', 'admin@example.test')
        ->expectsQuestion('Password', $password)
        ->expectsQuestion('Confirm password', $password)
        ->assertSuccessful();

    $user = User::query()->where('email', 'admin@example.test')->firstOrFail();
    expect($user->isSuperAdmin())->toBeTrue()
        ->and(Hash::check($password, $user->password))->toBeTrue();
    $this->assertDatabaseHas('administrative_audits', [
        'user_id' => $user->id, 'action' => 'security.super-admin.created', 'type' => 'security',
    ]);
    $this->assertDatabaseMissing('administrative_audits', ['record' => $password]);
});

it('refuses to bootstrap a second super admin', function () {
    User::factory()->superAdmin()->create();

    $this->artisan('user:create-first-super-admin')->assertFailed();
    $this->assertDatabaseCount('users', 1);
});

it('rejects mismatched passwords without creating an account', function () {
    $this->artisan('user:create-first-super-admin')
        ->expectsQuestion('Full name', 'Barangay Administrator')
        ->expectsQuestion('Email address', 'admin@example.test')
        ->expectsQuestion('Password', 'A-strong-password-123')
        ->expectsQuestion('Confirm password', 'different-password')
        ->assertFailed();

    $this->assertDatabaseEmpty('users');
    $this->assertDatabaseEmpty('administrative_audits');
});

it('rejects an email already used by a staff account', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    $this->artisan('user:create-first-super-admin')
        ->expectsQuestion('Full name', 'Barangay Administrator')
        ->expectsQuestion('Email address', $staff->email)
        ->expectsQuestion('Password', 'A-strong-password-123')
        ->expectsQuestion('Confirm password', 'A-strong-password-123')
        ->assertFailed();

    $this->assertDatabaseCount('users', 1);
});

it('explains why promotion cannot use an unknown email', function () {
    $this->artisan('user:grant-super-admin', ['email' => 'example@example.test'])
        ->expectsOutputToContain('No account matches that email')
        ->assertFailed();
});

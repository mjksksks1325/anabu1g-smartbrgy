<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('lists legacy roles and explicit VAWC holders without changing any account', function () {
    User::factory()->superAdmin()->create(['name' => 'Report Super Admin']);
    User::factory()->create(['role' => 'admin', 'is_super_admin' => false, 'name' => 'Legacy Admin Person']);
    User::factory()->create(['role' => 'viewer', 'name' => 'Legacy Viewer Person']);
    User::factory()->create(['role' => 'staff', 'name' => 'Desk Officer', 'email' => 'desk-private@example.test', 'staff_permissions' => ['vawc.view', 'vawc.update']]);
    $before = DB::table('users')->orderBy('id')->get()->toJson();

    $this->artisan('personnel:access-report')
        ->expectsOutputToContain('Legacy Admin Person')
        ->expectsOutputToContain('Legacy Viewer Person')
        ->expectsOutputToContain('Desk Officer')
        ->expectsOutputToContain('Explicit VAWC assignment is OFF')
        ->expectsOutputToContain('Ready: at least one active account explicitly holds vawc.view and vawc.update.')
        ->doesntExpectOutputToContain('desk-private@example.test')
        ->assertSuccessful();

    expect(DB::table('users')->orderBy('id')->get()->toJson())->toBe($before);
});

it('warns that turning explicit assignment on would leave cases without a handler when nobody qualifies', function () {
    User::factory()->create(['role' => 'staff', 'staff_permissions' => ['vawc.view']]);
    User::factory()->create(['role' => 'staff', 'is_active' => false, 'staff_permissions' => ['vawc.view', 'vawc.update']]);

    $this->artisan('personnel:access-report')
        ->expectsOutputToContain('Not ready: no active account explicitly holds both vawc.view and vawc.update.')
        ->assertSuccessful();
});

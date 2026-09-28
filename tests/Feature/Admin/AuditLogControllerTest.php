<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('requires super admin access to read administrative history', function () {
    $this->getJson(route('admin.audit.index'))->assertUnauthorized();
    $this->actingAs(User::factory()->create(['role' => 'viewer']))
        ->getJson(route('admin.audit.index'))->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->getJson(route('admin.audit.index'))->assertForbidden();
    $this->actingAs(User::factory()->resident()->create(), 'resident')
        ->getJson(route('admin.audit.index'))->assertForbidden();
});

it('excludes resident portal sign ins while retaining resident security history and personnel login events', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $resident = User::factory()->resident()->create();
    DB::table('administrative_audits')->insert([
        [
            'user_id' => $resident->id, 'actor' => $resident->name, 'action' => 'auth.login',
            'type' => 'auth', 'record' => 'resident_portal', 'created_at' => now(), 'updated_at' => now(),
        ],
        [
            'user_id' => $superAdmin->id, 'actor' => $superAdmin->name, 'action' => 'auth.login',
            'type' => 'auth', 'record' => null, 'created_at' => now(), 'updated_at' => now(),
        ],
        [
            'user_id' => $superAdmin->id, 'actor' => $superAdmin->name, 'action' => 'security.super-admin.granted',
            'type' => 'security', 'record' => null, 'created_at' => now(), 'updated_at' => now(),
        ],
    ]);

    $this->actingAs($superAdmin)->getJson(route('admin.audit.index'))
        ->assertOk()->assertJsonCount(2, 'events')->assertDontSee('Resident portal sign in');
    $this->assertDatabaseHas('administrative_audits', [
        'user_id' => $resident->id, 'action' => 'auth.login', 'record' => 'resident_portal',
    ]);
});

it('returns saved audit events without request payloads or credentials', function () {
    $staff = User::factory()->superAdmin()->create();
    DB::table('administrative_audits')->insert([
        'user_id' => $staff->id, 'actor' => $staff->name, 'action' => 'admin.residents.update',
        'type' => 'record', 'record' => '12', 'created_at' => '2026-09-20 08:30:00', 'updated_at' => '2026-09-20 08:30:00',
    ]);

    $this->actingAs($staff)->getJson(route('admin.audit.index'))->assertOk()
        ->assertJsonCount(1, 'events')->assertJsonPath('events.0.action', 'residents / update')
        ->assertJsonPath('events.0.date', '2026-09-20')->assertJsonPath('events.0.detail', 'Record ID: 12');
});

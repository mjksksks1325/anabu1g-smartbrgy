<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('requires staff access to read administrative history', function () {
    $this->getJson(route('admin.audit.index'))->assertUnauthorized();
    $this->actingAs(User::factory()->create(['role' => 'viewer']))
        ->getJson(route('admin.audit.index'))->assertForbidden();
});

it('returns saved audit events without request payloads or credentials', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    DB::table('administrative_audits')->insert([
        'user_id' => $staff->id, 'actor' => $staff->name, 'action' => 'admin.residents.update',
        'type' => 'record', 'record' => '12', 'created_at' => '2026-09-20 08:30:00', 'updated_at' => '2026-09-20 08:30:00',
    ]);

    $this->actingAs($staff)->getJson(route('admin.audit.index'))->assertOk()
        ->assertJsonCount(1, 'events')->assertJsonPath('events.0.action', 'residents / update')
        ->assertJsonPath('events.0.date', '2026-09-20')->assertJsonPath('events.0.detail', 'Record ID: 12');
});

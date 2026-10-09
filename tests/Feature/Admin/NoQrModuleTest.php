<?php

use App\Models\User;

it('does not render the QR verification module for any employee role', function (string $role) {
    $employee = $role === 'super' ? User::factory()->superAdmin()->create() : User::factory()->assignedOperations()->create(['role' => $role]);

    if ($role === 'admin') {
        $this->actingAs($employee)->get(route('staff.dashboard'))->assertRedirect(route('staff.access-pending'));
        $this->get(route('staff.access-pending'))->assertOk()->assertDontSee('QR Verification');

        return;
    }

    $this->actingAs($employee)->get(route('staff.dashboard', ['screen' => 'qr']))
        ->assertRedirect(route($employee->role === 'admin' ? 'admin.dashboard' : 'staff.dashboard'));
    $this->get(route('staff.dashboard'))
        ->assertOk()
        ->assertDontSee('data-perm="QR"', false)
        ->assertDontSee('id="screen-qr"', false)
        ->assertDontSee('id="modal-qr-verify"', false)
        ->assertDontSee('QR Verification')
        ->assertDontSee('Open QR Scanner');
})->with(['staff', 'admin', 'super']);

it('does not grant legacy viewers module access through stored assignments', function () {
    $viewer = User::factory()->assignedOperations()->create(['role' => 'viewer']);

    $this->actingAs($viewer)->get(route('staff.rfid-files.index'))
        ->assertForbidden()
        ->assertDontSee('QR Verification');
});

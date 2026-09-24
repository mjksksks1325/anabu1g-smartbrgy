<?php

use App\Models\User;

it('does not render the QR verification module for any employee role', function (string $role) {
    $employee = $role === 'super' ? User::factory()->superAdmin()->create() : User::factory()->create(['role' => $role]);

    $this->actingAs($employee)->get(route('admin.dashboard', ['screen' => 'qr']))
        ->assertOk()
        ->assertDontSee('data-perm="QR"', false)
        ->assertDontSee('id="screen-qr"', false)
        ->assertDontSee('id="modal-qr-verify"', false)
        ->assertDontSee('QR Verification')
        ->assertDontSee('Open QR Scanner');
})->with(['staff', 'admin', 'super']);

it('does not show the removed module in the viewer workspace', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);

    $this->actingAs($viewer)->get(route('admin.rfid-files.index'))
        ->assertOk()
        ->assertDontSee('QR Verification');
});

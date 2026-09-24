<?php

use App\Models\EmployeeCabinetAccess;
use App\Models\FileMovementEvent;
use App\Models\User;

it('lets a Super Admin link an employee to an existing Pi identity without completing enrollment', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $employee = User::factory()->create(['role' => 'staff']);

    $this->actingAs($superAdmin)->get(route('admin.cabinet-access.index'))
        ->assertOk()->assertSee('Not linked')->assertSee('name="rpi_employee_id"', false);
    $this->patch(route('admin.cabinet-access.rpi-employee-id.update', $employee), [
        'rpi_employee_id' => '  emp001  ',
        'is_active' => '1',
        'rfid_enrollment_status' => EmployeeCabinetAccess::ENROLLED,
        'face_enrollment_status' => EmployeeCabinetAccess::ENROLLED,
    ])->assertRedirect()->assertSessionHas('status', 'RPi Employee ID saved.');

    $access = $employee->cabinetAccess()->firstOrFail();
    expect($access->rpi_employee_id)->toBe('EMP001')
        ->and($access->is_active)->toBeFalse()
        ->and($access->rfid_enrollment_status)->toBe(EmployeeCabinetAccess::NOT_STARTED)
        ->and($access->face_enrollment_status)->toBe(EmployeeCabinetAccess::NOT_STARTED)
        ->and($access->rfid_enrolled_at)->toBeNull()
        ->and($access->face_enrolled_at)->toBeNull()
        ->and($employee->fresh()->role)->toBe('staff');
    $this->get(route('admin.cabinet-access.index'))->assertSee('EMP001');
    $this->assertDatabaseHas('administrative_audits', [
        'action' => 'admin.cabinet-access.rpi-employee-id.update', 'type' => 'security', 'user_id' => $superAdmin->id,
    ]);
});

it('updates and clears a mapping without changing existing enrollment or cabinet permission', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $employee = User::factory()->create(['role' => 'staff']);
    $access = EmployeeCabinetAccess::factory()->create([
        'user_id' => $employee->id,
        'rpi_employee_id' => 'EMP001',
        'is_active' => true,
        'rfid_enrollment_status' => EmployeeCabinetAccess::PENDING,
        'face_enrollment_status' => EmployeeCabinetAccess::PENDING,
    ]);

    $this->actingAs($superAdmin)->patch(route('admin.cabinet-access.rpi-employee-id.update', $employee), [
        'rpi_employee_id' => 'EMP001',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->patch(route('admin.cabinet-access.rpi-employee-id.update', $employee), [
        'rpi_employee_id' => ' tech-7 ',
    ])->assertRedirect();
    expect($access->fresh()->rpi_employee_id)->toBe('TECH-7')
        ->and($access->fresh()->is_active)->toBeTrue()
        ->and($access->fresh()->rfid_enrollment_status)->toBe(EmployeeCabinetAccess::PENDING)
        ->and($access->fresh()->face_enrollment_status)->toBe(EmployeeCabinetAccess::PENDING);

    $this->patch(route('admin.cabinet-access.rpi-employee-id.update', $employee), ['rpi_employee_id' => '  '])
        ->assertRedirect()->assertSessionHas('status', 'RPi Employee ID unlinked.');
    expect($access->fresh()->rpi_employee_id)->toBeNull();
    $this->get(route('admin.cabinet-access.index'))->assertSee('Not linked');
});

it('rejects an RPi employee ID already linked to another employee', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    EmployeeCabinetAccess::factory()->create(['rpi_employee_id' => 'EMP001']);
    $employee = User::factory()->create(['role' => 'staff']);

    $this->actingAs($superAdmin)->patch(route('admin.cabinet-access.rpi-employee-id.update', $employee), [
        'rpi_employee_id' => ' emp001 ',
    ])->assertSessionHasErrors('rpi_employee_id');

    expect($employee->cabinetAccess()->exists())->toBeFalse();
    $this->assertDatabaseCount('employee_cabinet_access', 1);
});

it('rejects malformed or overlong RPi employee IDs', function (string $invalidId) {
    $superAdmin = User::factory()->superAdmin()->create();
    $employee = User::factory()->create(['role' => 'staff']);

    $this->actingAs($superAdmin)->patch(route('admin.cabinet-access.rpi-employee-id.update', $employee), [
        'rpi_employee_id' => $invalidId,
    ])->assertSessionHasErrors('rpi_employee_id');

    expect($employee->cabinetAccess()->exists())->toBeFalse();
})->with([
    'overlong' => str_repeat('A', 33),
    'starts with a digit' => '001EMP',
    'contains spaces' => 'EMP 001',
    'contains punctuation' => 'EMP/001',
]);

it('rejects mapping changes from every non-Super Admin role', function (string $role) {
    $actor = $role === 'resident'
        ? User::factory()->resident()->create()
        : User::factory()->create(['role' => $role]);
    $employee = User::factory()->create(['role' => 'staff']);

    $this->actingAs($actor)->patch(route('admin.cabinet-access.rpi-employee-id.update', $employee), [
        'rpi_employee_id' => 'EMP001',
    ])->assertForbidden();

    expect($employee->cabinetAccess()->exists())->toBeFalse();
})->with(['admin', 'staff', 'viewer', 'resident']);

it('keeps the stable Pi mapping and file history after cabinet access is disabled', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $employee = User::factory()->create(['role' => 'staff']);
    $access = EmployeeCabinetAccess::factory()->create([
        'user_id' => $employee->id, 'is_active' => true, 'rpi_employee_id' => 'EMP004',
    ]);
    $movement = FileMovementEvent::factory()->create(['user_id' => $employee->id]);

    $this->actingAs($superAdmin)->patch(route('admin.cabinet-access.update', $employee), [
        'is_active' => '0',
    ])->assertRedirect();

    expect($access->fresh()->is_active)->toBeFalse()
        ->and($access->fresh()->rpi_employee_id)->toBe('EMP004');
    $this->assertModelExists($movement);
});

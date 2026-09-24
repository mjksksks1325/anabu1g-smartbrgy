<?php

use App\Models\CabinetDevice;
use App\Models\EmployeeCabinetAccess;
use App\Models\FileMovementEvent;
use App\Models\User;

it('separates employee RFID access from super admin pages', function () {
    foreach (['admin.rfid-files.index', 'admin.smart-cabinet.index', 'admin.cabinet-access.index'] as $route) {
        $this->get(route($route))->assertRedirect();
    }

    $staff = User::factory()->create(['role' => 'staff']);
    $this->actingAs($staff)->get(route('admin.rfid-files.index'))->assertOk()->assertSee('No file movements recorded');
    $this->get(route('admin.smart-cabinet.index'))->assertForbidden();
    $this->get(route('admin.cabinet-access.index'))->assertForbidden();
    $this->getJson(route('admin.audit.index'))->assertForbidden();
    $this->getJson(route('admin.users.index'))->assertForbidden();

    $ordinaryAdmin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($ordinaryAdmin)->get(route('admin.rfid-files.index'))->assertOk();
    $this->get(route('admin.smart-cabinet.index'))->assertForbidden();

    $superAdmin = User::factory()->superAdmin()->create();
    $this->actingAs($superAdmin)->get(route('admin.smart-cabinet.index'))->assertOk()->assertSee('No cabinets registered')
        ->assertSee('iot-theme-toggle');
    $this->get(route('admin.cabinet-access.index'))->assertOk()->assertSee('No employees have completed cabinet enrollment yet.');
});

it('uses saved file movements and filters instead of browser simulation', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $cabinet = CabinetDevice::factory()->create(['identifier' => 'CAB-01', 'name' => 'Records cabinet']);
    FileMovementEvent::factory()->create(['file_reference' => 'FILE-001', 'file_name' => 'Permit folder', 'user_id' => $staff->id,
        'cabinet_device_id' => $cabinet->id, 'drawer_reference' => 'A-1', 'action' => 'removed', 'occurred_at' => now()->subHour()]);
    FileMovementEvent::factory()->create(['file_reference' => 'FILE-001', 'file_name' => 'Permit folder', 'user_id' => $staff->id,
        'cabinet_device_id' => $cabinet->id, 'drawer_reference' => 'A-1', 'action' => 'returned', 'occurred_at' => now()]);
    FileMovementEvent::factory()->create(['file_reference' => 'FILE-002', 'file_name' => 'Other folder', 'user_id' => $staff->id,
        'action' => 'removed', 'occurred_at' => now()]);

    $this->actingAs($staff)->get(route('admin.rfid-files.index', ['search' => 'FILE-001', 'action' => 'removed']))
        ->assertOk()->assertSee('Permit folder')->assertSee('In cabinet')->assertDontSee('Other folder');
    $this->get(route('admin.rfid-files.index', ['action' => 'unlocked']))->assertSessionHasErrors('action');
});

it('shows unknown cabinet status until a recent device report exists', function () {
    $cabinet = CabinetDevice::factory()->create(['last_seen_at' => null, 'reported_status' => null]);
    expect($cabinet->connectionStatus())->toBe('unknown');
    $cabinet->forceFill(['last_seen_at' => now()->subMinutes(10), 'reported_status' => 'online'])->save();
    expect($cabinet->fresh()->connectionStatus())->toBe('offline');
    $cabinet->forceFill(['last_seen_at' => now(), 'reported_status' => 'online'])->save();
    expect($cabinet->fresh()->connectionStatus())->toBe('online');
});

it('keeps cabinet permission separate from website role and enrollment', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $employee = User::factory()->create(['role' => 'staff']);

    $this->actingAs($superAdmin)->patch(route('admin.cabinet-access.update', $employee), ['is_active' => '1'])
        ->assertRedirect()->assertSessionHas('status');
    $access = $employee->cabinetAccess()->firstOrFail();
    expect($access->is_active)->toBeTrue()->and($access->isEffective())->toBeFalse()
        ->and($employee->fresh()->role)->toBe('staff');
    $this->get(route('admin.cabinet-access.index'))->assertOk()->assertSee('Deactivate cabinet access?');
    $this->post(route('admin.cabinet-access.enroll', [$employee, 'rfid']))->assertRedirect();
    expect($access->fresh()->rfid_enrollment_status)->toBe(EmployeeCabinetAccess::PENDING);
    $this->post(route('admin.cabinet-access.enroll', [$employee, 'rfid']))->assertSessionHasErrors('enrollment');
    $this->patch(route('admin.cabinet-access.update', $employee), ['is_active' => '0'])->assertRedirect();
    expect($access->fresh()->is_active)->toBeFalse()->and($access->fresh()->authorization_version)->toBeGreaterThan(1);
    $this->assertDatabaseCount('employee_cabinet_access', 1);
    $this->assertDatabaseHas('administrative_audits', ['action' => 'admin.cabinet-access.enroll.initiated', 'type' => 'security']);
});

it('rejects cabinet mutations by normal users', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $target = User::factory()->create(['role' => 'staff']);
    $this->actingAs($staff)->patch(route('admin.cabinet-access.update', $target), ['is_active' => '1'])->assertForbidden();
    $this->post(route('admin.cabinet-access.enroll', [$target, 'rfid']))->assertForbidden();
    $this->assertDatabaseEmpty('employee_cabinet_access');
});

it('allows read only employees to view tracking but never administration', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);
    $this->actingAs($viewer)->get(route('admin.rfid-files.index'))->assertOk()->assertDontSee('href="'.route('admin.dashboard').'"', false);
    $this->get(route('admin.smart-cabinet.index'))->assertForbidden();
    $this->get(route('admin.cabinet-access.index'))->assertForbidden();
});

it('denies resident accounts access to all employee IoT pages', function () {
    $resident = User::factory()->resident()->create();
    foreach (['admin.rfid-files.index', 'admin.smart-cabinet.index', 'admin.cabinet-access.index'] as $route) {
        $this->actingAs($resident, 'resident')->get(route($route))->assertForbidden();
    }
});

it('paginates file movements without discarding their history', function () {
    $employee = User::factory()->create(['role' => 'staff']);
    FileMovementEvent::factory()->count(21)->create(['user_id' => $employee->id]);
    $this->actingAs($employee)->get(route('admin.rfid-files.index'))->assertOk()->assertSee('21 recorded events');
    $this->get(route('admin.rfid-files.index', ['page' => 2]))->assertOk()->assertSee('Movement history');
    $this->assertDatabaseCount('file_movement_events', 21);
});

it('keeps historical movements after cabinet access is deactivated', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $employee = User::factory()->create(['role' => 'staff']);
    $access = EmployeeCabinetAccess::factory()->create([
        'user_id' => $employee->id,
        'is_active' => true,
        'rpi_employee_id' => 'EMP003',
    ]);
    $movement = FileMovementEvent::factory()->create(['user_id' => $employee->id]);

    $this->actingAs($superAdmin)->patch(route('admin.cabinet-access.update', $employee), ['is_active' => '0'])->assertRedirect();
    expect($access->fresh()->is_active)->toBeFalse()
        ->and($access->fresh()->rpi_employee_id)->toBe('EMP003');
    $this->assertDatabaseHas('file_movement_events', ['id' => $movement->id, 'user_id' => $employee->id]);
    $this->assertDatabaseHas('administrative_audits', ['action' => 'admin.cabinet-access.update.disabled', 'type' => 'security']);
});

it('invalidates effective cabinet access when the website account is suspended', function () {
    $employee = User::factory()->create(['role' => 'staff']);
    $access = EmployeeCabinetAccess::factory()->create([
        'user_id' => $employee->id,
        'is_active' => true,
        'rfid_enrollment_status' => EmployeeCabinetAccess::ENROLLED,
        'face_enrollment_status' => EmployeeCabinetAccess::ENROLLED,
    ]);
    expect($access->isEffective())->toBeTrue();

    $employee->forceFill(['is_active' => false])->save();
    expect($access->fresh()->isEffective())->toBeFalse()
        ->and($access->is_active)->toBeTrue();
});

it('refuses to grant super admin to a normal employee', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $this->artisan('user:grant-super-admin', ['email' => $staff->email])->assertFailed();
    expect($staff->fresh()->is_super_admin)->toBeFalse();
});

it('does not promote existing administrators automatically', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    expect($admin->isSuperAdmin())->toBeFalse();
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertDontSee('Employee Cabinet Access');

    $this->artisan('user:grant-super-admin', ['email' => $admin->email])->assertSuccessful();
    expect($admin->fresh()->isSuperAdmin())->toBeTrue();
    $this->actingAs($admin->fresh())->get(route('admin.dashboard'))->assertOk()->assertSee('Employee Cabinet Access');
    $this->assertDatabaseHas('administrative_audits', ['action' => 'security.super-admin.granted', 'user_id' => $admin->id]);
    $this->artisan('user:grant-super-admin', ['email' => $admin->email, '--revoke' => true])->assertFailed();
});

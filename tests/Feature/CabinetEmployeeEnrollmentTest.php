<?php

use App\Models\CabinetDevice;
use App\Models\EmployeeCabinetAccess;
use App\Models\User;
use Illuminate\Support\Carbon;

it('confirms RFID enrollment for a mapped Pi employee without enabling cabinet access', function () {
    $cabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'device-token')]);
    $employee = User::factory()->create(['role' => 'staff']);
    $access = EmployeeCabinetAccess::factory()->create(['user_id' => $employee->id, 'rpi_employee_id' => 'EMP001']);

    $this->withHeader('X-Device-Token', 'device-token')
        ->postJson(route('api.iot.cabinets.employee-enrollment', $cabinet), [
            'rpi_employee_id' => ' emp001 ', 'rfid_enrolled' => true,
        ])->assertOk()->assertExactJson([
            'rpi_employee_id' => 'EMP001',
            'rfid_enrollment_status' => EmployeeCabinetAccess::ENROLLED,
            'face_enrollment_status' => EmployeeCabinetAccess::NOT_STARTED,
            'effective_access' => false,
        ]);

    $access->refresh();
    expect($access->rfid_enrolled_at)->not->toBeNull()
        ->and($access->face_enrolled_at)->toBeNull()
        ->and($access->is_active)->toBeFalse()
        ->and($access->authorization_version)->toBe(1)
        ->and($employee->fresh()->role)->toBe('staff');
    $this->assertDatabaseCount('employee_cabinet_access', 1);
});

it('confirms face enrollment without altering RFID enrollment', function () {
    $cabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'device-token')]);
    $access = EmployeeCabinetAccess::factory()->create(['rpi_employee_id' => 'EMP001']);

    $this->withHeader('X-Device-Token', 'device-token')
        ->postJson(route('api.iot.cabinets.employee-enrollment', $cabinet), [
            'rpi_employee_id' => 'EMP001', 'face_enrolled' => true,
        ])->assertOk()->assertJsonPath('face_enrollment_status', EmployeeCabinetAccess::ENROLLED)
        ->assertJsonPath('rfid_enrollment_status', EmployeeCabinetAccess::NOT_STARTED);

    $access->refresh();
    expect($access->face_enrolled_at)->not->toBeNull()
        ->and($access->rfid_enrolled_at)->toBeNull();
});

it('confirms both credentials together and reports effective access only when permission is active', function () {
    $cabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'device-token')]);
    $employee = User::factory()->create(['role' => 'staff']);
    $access = EmployeeCabinetAccess::factory()->create([
        'user_id' => $employee->id, 'rpi_employee_id' => 'EMP001', 'is_active' => true,
        'rfid_enrollment_status' => EmployeeCabinetAccess::PENDING,
        'face_enrollment_status' => EmployeeCabinetAccess::PENDING,
        'authorization_version' => 4,
    ]);

    $this->withHeader('X-Device-Token', 'device-token')
        ->postJson(route('api.iot.cabinets.employee-enrollment', $cabinet), [
            'rpi_employee_id' => 'EMP001', 'rfid_enrolled' => true, 'face_enrolled' => true,
        ])->assertOk()->assertJsonPath('effective_access', true);

    $access->refresh();
    expect($access->rfid_enrollment_status)->toBe(EmployeeCabinetAccess::ENROLLED)
        ->and($access->face_enrollment_status)->toBe(EmployeeCabinetAccess::ENROLLED)
        ->and($access->rfid_enrolled_at)->not->toBeNull()
        ->and($access->face_enrolled_at)->not->toBeNull()
        ->and($access->authorization_version)->toBe(4)
        ->and($access->is_active)->toBeTrue();
});

it('keeps enrollment timestamps and authorization version unchanged on repeated confirmation', function () {
    $this->travelTo(Carbon::parse('2026-09-24 08:00:00'));
    $cabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'device-token')]);
    $access = EmployeeCabinetAccess::factory()->create(['rpi_employee_id' => 'EMP001', 'authorization_version' => 7]);
    $payload = ['rpi_employee_id' => 'EMP001', 'rfid_enrolled' => true, 'face_enrolled' => true];

    $this->withHeader('X-Device-Token', 'device-token')
        ->postJson(route('api.iot.cabinets.employee-enrollment', $cabinet), $payload)->assertOk();
    $first = $access->fresh();
    $this->travelTo(Carbon::parse('2026-09-25 08:00:00'));
    $this->postJson(route('api.iot.cabinets.employee-enrollment', $cabinet), $payload)->assertOk();

    $repeated = $access->fresh();
    expect($repeated->rfid_enrolled_at->toDateTimeString())->toBe($first->rfid_enrolled_at->toDateTimeString())
        ->and($repeated->face_enrolled_at->toDateTimeString())->toBe($first->face_enrolled_at->toDateTimeString())
        ->and($repeated->updated_at->toDateTimeString())->toBe($first->updated_at->toDateTimeString())
        ->and($repeated->authorization_version)->toBe(7);
    $this->assertDatabaseCount('employee_cabinet_access', 1);
    $this->travelBack();
});

it('returns not found for an unknown Pi employee ID without creating a mapping', function () {
    $cabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'device-token')]);
    EmployeeCabinetAccess::factory()->create(['rpi_employee_id' => null]);

    $this->withHeader('X-Device-Token', 'device-token')
        ->postJson(route('api.iot.cabinets.employee-enrollment', $cabinet), [
            'rpi_employee_id' => 'EMP999', 'rfid_enrolled' => true,
        ])->assertNotFound();

    $this->assertDatabaseCount('employee_cabinet_access', 1);
    $this->assertDatabaseMissing('employee_cabinet_access', ['rpi_employee_id' => 'EMP999']);
});

it('rejects missing and invalid device tokens before touching enrollment', function () {
    $cabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'device-token')]);
    $access = EmployeeCabinetAccess::factory()->create(['rpi_employee_id' => 'EMP001']);
    $payload = ['rpi_employee_id' => 'EMP001', 'rfid_enrolled' => true];

    $this->postJson(route('api.iot.cabinets.employee-enrollment', $cabinet), $payload)->assertUnauthorized();
    $this->withHeader('X-Device-Token', 'wrong-token')
        ->postJson(route('api.iot.cabinets.employee-enrollment', $cabinet), $payload)->assertUnauthorized();

    expect($access->fresh()->rfid_enrollment_status)->toBe(EmployeeCabinetAccess::NOT_STARTED);
});

it('rejects false enrollment reports instead of revoking an existing confirmation', function () {
    $cabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'device-token')]);
    $access = EmployeeCabinetAccess::factory()->create([
        'rpi_employee_id' => 'EMP001',
        'rfid_enrollment_status' => EmployeeCabinetAccess::ENROLLED,
        'rfid_enrolled_at' => now()->subDay(),
    ]);
    $originalTimestamp = $access->rfid_enrolled_at->toDateTimeString();

    $this->withHeader('X-Device-Token', 'device-token')
        ->postJson(route('api.iot.cabinets.employee-enrollment', $cabinet), [
            'rpi_employee_id' => 'EMP001', 'rfid_enrolled' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('rfid_enrolled');

    expect($access->fresh()->rfid_enrollment_status)->toBe(EmployeeCabinetAccess::ENROLLED)
        ->and($access->fresh()->rfid_enrolled_at->toDateTimeString())->toBe($originalTimestamp);
});

it('rejects sensitive or identity-changing payload fields without storing them', function () {
    $cabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'device-token')]);
    $employee = User::factory()->create(['role' => 'staff']);
    $access = EmployeeCabinetAccess::factory()->create(['user_id' => $employee->id, 'rpi_employee_id' => 'EMP001']);

    $this->withHeader('X-Device-Token', 'device-token')
        ->postJson(route('api.iot.cabinets.employee-enrollment', $cabinet), [
            'rpi_employee_id' => 'EMP001', 'rfid_enrolled' => true,
            'rfid_uid' => 'SECRET-CARD-UID', 'face_template' => 'SECRET-TEMPLATE',
            'face_image' => 'SECRET-IMAGE', 'user_id' => $employee->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('payload')
        ->assertDontSee('SECRET-CARD-UID')->assertDontSee('SECRET-TEMPLATE')->assertDontSee('SECRET-IMAGE');

    expect($access->fresh()->rfid_enrollment_status)->toBe(EmployeeCabinetAccess::NOT_STARTED)
        ->and($employee->fresh()->role)->toBe('staff');
});

it('requires a valid Pi employee ID and at least one true confirmation', function () {
    $cabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'device-token')]);
    $access = EmployeeCabinetAccess::factory()->create(['rpi_employee_id' => 'EMP001']);

    $this->withHeader('X-Device-Token', 'device-token')
        ->postJson(route('api.iot.cabinets.employee-enrollment', $cabinet), [
            'rpi_employee_id' => 'EMP001',
        ])->assertUnprocessable()->assertJsonValidationErrors('enrollment');
    $this->postJson(route('api.iot.cabinets.employee-enrollment', $cabinet), [
        'rpi_employee_id' => '001EMP', 'rfid_enrolled' => true,
    ])->assertUnprocessable()->assertJsonValidationErrors('rpi_employee_id');
    $this->postJson(route('api.iot.cabinets.employee-enrollment', $cabinet), [
        'rpi_employee_id' => str_repeat('A', 33), 'face_enrolled' => true,
    ])->assertUnprocessable()->assertJsonValidationErrors('rpi_employee_id');

    expect($access->fresh()->rfid_enrollment_status)->toBe(EmployeeCabinetAccess::NOT_STARTED)
        ->and($access->fresh()->face_enrollment_status)->toBe(EmployeeCabinetAccess::NOT_STARTED);
});

it('reports ineffective access when the mapped website employee is suspended', function () {
    $cabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'device-token')]);
    $employee = User::factory()->create(['role' => 'staff', 'is_active' => false]);
    $access = EmployeeCabinetAccess::factory()->create([
        'user_id' => $employee->id, 'rpi_employee_id' => 'EMP001', 'is_active' => true,
    ]);

    $this->withHeader('X-Device-Token', 'device-token')
        ->postJson(route('api.iot.cabinets.employee-enrollment', $cabinet), [
            'rpi_employee_id' => 'EMP001', 'rfid_enrolled' => true, 'face_enrolled' => true,
        ])->assertOk()->assertJsonPath('effective_access', false);

    expect($access->fresh()->is_active)->toBeTrue()
        ->and($employee->fresh()->is_active)->toBeFalse();
});

it('returns the next active employee requiring cabinet enrollment', function () {
    $cabinet = CabinetDevice::factory()->create([
        'api_token_hash' => hash('sha256', 'device-token'),
    ]);

    $employee = User::factory()->create([
        'name' => 'Jerome Landig',
        'role' => 'staff',
        'is_active' => true,
    ]);

    EmployeeCabinetAccess::factory()->create([
        'user_id' => $employee->id,
        'rpi_employee_id' => 'EMP005',
        'is_active' => true,
        'rfid_enrollment_status' => EmployeeCabinetAccess::NOT_STARTED,
        'face_enrollment_status' => EmployeeCabinetAccess::NOT_STARTED,
        'authorization_version' => 1,
    ]);

    $this->withHeader('X-Device-Token', 'device-token')
        ->getJson(route('api.iot.cabinets.employee-enrollment.pending', $cabinet))
        ->assertOk()
        ->assertExactJson([
            'pending' => true,
            'employee' => [
                'rpi_employee_id' => 'EMP005',
                'name' => 'Jerome Landig',
                'rfid_enrollment_required' => true,
                'face_enrollment_required' => true,
                'authorization_version' => 1,
            ],
        ]);
});

it('reports only the missing credential when enrollment is partially complete', function () {
    $cabinet = CabinetDevice::factory()->create([
        'api_token_hash' => hash('sha256', 'device-token'),
    ]);

    $employee = User::factory()->create([
        'role' => 'staff',
        'is_active' => true,
    ]);

    EmployeeCabinetAccess::factory()->create([
        'user_id' => $employee->id,
        'rpi_employee_id' => 'EMP005',
        'is_active' => true,
        'rfid_enrollment_status' => EmployeeCabinetAccess::ENROLLED,
        'face_enrollment_status' => EmployeeCabinetAccess::NOT_STARTED,
    ]);

    $this->withHeader('X-Device-Token', 'device-token')
        ->getJson(route('api.iot.cabinets.employee-enrollment.pending', $cabinet))
        ->assertOk()
        ->assertJsonPath('pending', true)
        ->assertJsonPath('employee.rpi_employee_id', 'EMP005')
        ->assertJsonPath('employee.rfid_enrollment_required', false)
        ->assertJsonPath('employee.face_enrollment_required', true);
});

it('does not return completed disabled suspended or viewer cabinet employees as pending', function () {
    $cabinet = CabinetDevice::factory()->create([
        'api_token_hash' => hash('sha256', 'device-token'),
    ]);

    $completed = User::factory()->create(['role' => 'staff', 'is_active' => true]);
    EmployeeCabinetAccess::factory()->create([
        'user_id' => $completed->id,
        'rpi_employee_id' => 'EMP001',
        'is_active' => true,
        'rfid_enrollment_status' => EmployeeCabinetAccess::ENROLLED,
        'face_enrollment_status' => EmployeeCabinetAccess::ENROLLED,
    ]);

    $disabled = User::factory()->create(['role' => 'staff', 'is_active' => true]);
    EmployeeCabinetAccess::factory()->create([
        'user_id' => $disabled->id,
        'rpi_employee_id' => 'EMP002',
        'is_active' => false,
    ]);

    $suspended = User::factory()->create(['role' => 'staff', 'is_active' => false]);
    EmployeeCabinetAccess::factory()->create([
        'user_id' => $suspended->id,
        'rpi_employee_id' => 'EMP003',
        'is_active' => true,
    ]);

    $viewer = User::factory()->create(['role' => 'viewer', 'is_active' => true]);
    EmployeeCabinetAccess::factory()->create([
        'user_id' => $viewer->id,
        'rpi_employee_id' => 'EMP004',
        'is_active' => true,
    ]);

    $this->withHeader('X-Device-Token', 'device-token')
        ->getJson(route('api.iot.cabinets.employee-enrollment.pending', $cabinet))
        ->assertOk()
        ->assertExactJson([
            'pending' => false,
            'employee' => null,
        ]);
});

it('requires a valid device token to read pending enrollment', function () {
    $cabinet = CabinetDevice::factory()->create([
        'api_token_hash' => hash('sha256', 'device-token'),
    ]);

    $this->getJson(route('api.iot.cabinets.employee-enrollment.pending', $cabinet))
        ->assertUnauthorized();

    $this->withHeader('X-Device-Token', 'wrong-token')
        ->getJson(route('api.iot.cabinets.employee-enrollment.pending', $cabinet))
        ->assertUnauthorized();
});

<?php

use App\Models\CabinetDevice;
use App\Models\EmployeeCabinetAccess;
use App\Models\Resident;
use App\Models\User;

function mappedMovementEmployee(string $rpiEmployeeId = 'EMP001', array $userAttributes = [], array $accessAttributes = []): User
{
    $employee = User::factory()->create(array_merge(['role' => 'staff'], $userAttributes));
    EmployeeCabinetAccess::factory()->create(array_merge([
        'user_id' => $employee->id,
        'rpi_employee_id' => $rpiEmployeeId,
        'is_active' => true,
        'rfid_enrollment_status' => EmployeeCabinetAccess::ENROLLED,
        'face_enrollment_status' => EmployeeCabinetAccess::ENROLLED,
    ], $accessAttributes));

    return $employee;
}

function cabinetMovementPayload(array $overrides = []): array
{
    return array_merge([
        'event_id' => 'scan-001',
        'file_reference' => 'F-001',
        'file_name' => 'Budget Folder',
        'drawer_reference' => '2',
        'rpi_employee_id' => 'EMP001',
        'action' => 'removed',
        'occurred_at' => '2026-09-24T08:00:00+08:00',
    ], $overrides);
}

it('records a mapped employee removal without a Laravel user ID or folder RFID tag', function () {
    $cabinet = CabinetDevice::factory()->create(['identifier' => 'CAB-01', 'api_token_hash' => hash('sha256', 'local-device-token')]);
    $employee = mappedMovementEmployee();
    $payload = cabinetMovementPayload(['rpi_employee_id' => ' emp001 ']);

    $this->withHeader('X-Device-Token', 'local-device-token')
        ->postJson(route('api.iot.cabinets.movements', $cabinet), $payload)
        ->assertCreated()->assertJsonPath('duplicate', false);

    $this->assertDatabaseHas('file_movement_events', [
        'file_reference' => 'F-001', 'rfid_tag' => null, 'action' => 'removed',
        'cabinet_device_id' => $cabinet->id, 'user_id' => $employee->id,
        'device_event_id' => $cabinet->id.':scan-001',
    ]);
    expect($cabinet->fresh()->connectionStatus())->toBe('online');
    expect($cabinet->fresh()->last_seen_at)->not->toBeNull();
    $this->actingAs($employee)->get(route('admin.rfid-files.index'))->assertSee('Budget Folder');

    $this->withHeader('X-Device-Token', 'local-device-token')
        ->postJson(route('api.iot.cabinets.movements', $cabinet), $payload)
        ->assertOk()->assertJsonPath('duplicate', true);
    $this->assertDatabaseCount('file_movement_events', 1);
});

it('records returned movements separately and preserves append-only history', function () {
    $cabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'device-token')]);
    $employee = mappedMovementEmployee();
    $this->withHeader('X-Device-Token', 'device-token')
        ->postJson(route('api.iot.cabinets.movements', $cabinet), cabinetMovementPayload())->assertCreated();
    $this->postJson(route('api.iot.cabinets.movements', $cabinet), cabinetMovementPayload([
        'event_id' => 'scan-002', 'action' => 'returned', 'rfid_tag' => 'FOLDER-TAG-01',
    ]))->assertCreated();

    $this->assertDatabaseCount('file_movement_events', 2);
    $this->assertDatabaseHas('file_movement_events', [
        'device_event_id' => $cabinet->id.':scan-001', 'user_id' => $employee->id,
        'action' => 'removed', 'rfid_tag' => null,
    ]);
    $this->assertDatabaseHas('file_movement_events', [
        'device_event_id' => $cabinet->id.':scan-002', 'user_id' => $employee->id,
        'action' => 'returned', 'rfid_tag' => 'FOLDER-TAG-01',
    ]);
});

it('accepts an authorized admin mapping and scopes event IDs to each cabinet', function () {
    $firstCabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'device-token')]);
    $secondCabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'device-token')]);
    $employee = mappedMovementEmployee('EMP001', ['role' => 'admin']);
    $payload = cabinetMovementPayload();

    $this->withHeader('X-Device-Token', 'device-token')
        ->postJson(route('api.iot.cabinets.movements', $firstCabinet), $payload)->assertCreated();
    $this->postJson(route('api.iot.cabinets.movements', $secondCabinet), $payload)->assertCreated();

    $this->assertDatabaseCount('file_movement_events', 2);
    $this->assertDatabaseHas('file_movement_events', [
        'device_event_id' => $firstCabinet->id.':scan-001', 'user_id' => $employee->id,
    ]);
    $this->assertDatabaseHas('file_movement_events', [
        'device_event_id' => $secondCabinet->id.':scan-001', 'user_id' => $employee->id,
    ]);
});

it('rejects missing or incorrect device tokens before recording data', function () {
    $cabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'correct-token')]);
    mappedMovementEmployee();
    $payload = cabinetMovementPayload();

    $this->postJson(route('api.iot.cabinets.movements', $cabinet), $payload)->assertUnauthorized();
    $this->withHeader('X-Device-Token', 'wrong-token')
        ->postJson(route('api.iot.cabinets.movements', $cabinet), $payload)->assertUnauthorized();
    $this->assertDatabaseEmpty('file_movement_events');
});

it('rejects unknown Pi IDs and invalid movement values without recording data', function () {
    $cabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'device-token')]);
    mappedMovementEmployee();

    $this->withHeader('X-Device-Token', 'device-token')
        ->postJson(route('api.iot.cabinets.movements', $cabinet), cabinetMovementPayload(['rpi_employee_id' => 'EMP999']))
        ->assertUnprocessable()->assertJsonValidationErrors('rpi_employee_id');
    $this->postJson(route('api.iot.cabinets.movements', $cabinet), cabinetMovementPayload(['rpi_employee_id' => '001EMP']))
        ->assertUnprocessable()->assertJsonValidationErrors('rpi_employee_id');
    $this->postJson(route('api.iot.cabinets.movements', $cabinet), cabinetMovementPayload(['rpi_employee_id' => str_repeat('A', 33)]))
        ->assertUnprocessable()->assertJsonValidationErrors('rpi_employee_id');
    $this->postJson(route('api.iot.cabinets.movements', $cabinet), cabinetMovementPayload(['action' => 'out']))
        ->assertUnprocessable()->assertJsonValidationErrors('action');
    $this->assertDatabaseEmpty('file_movement_events');
});

it('rejects mapped users without active authorized effective cabinet access', function (array $userAttributes, array $accessAttributes) {
    $cabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'device-token')]);
    mappedMovementEmployee('EMP001', $userAttributes, $accessAttributes);

    $this->withHeader('X-Device-Token', 'device-token')
        ->postJson(route('api.iot.cabinets.movements', $cabinet), cabinetMovementPayload())
        ->assertUnprocessable()->assertJsonValidationErrors('rpi_employee_id');
    $this->assertDatabaseEmpty('file_movement_events');
})->with([
    'inactive website account' => [['is_active' => false], []],
    'resident-linked account' => [['resident_id' => Resident::factory()], []],
    'viewer role' => [['role' => 'viewer'], []],
    'disabled cabinet access' => [[], ['is_active' => false]],
    'RFID not enrolled' => [[], ['rfid_enrollment_status' => EmployeeCabinetAccess::NOT_STARTED]],
    'face not enrolled' => [[], ['face_enrollment_status' => EmployeeCabinetAccess::NOT_STARTED]],
]);

it('rejects numeric employee IDs and client-supplied names instead of trusting them', function () {
    $cabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'device-token')]);
    $employee = mappedMovementEmployee();
    $otherEmployee = User::factory()->create(['role' => 'staff']);

    $this->withHeader('X-Device-Token', 'device-token')
        ->postJson(route('api.iot.cabinets.movements', $cabinet), cabinetMovementPayload(['employee_id' => $otherEmployee->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('payload');
    $this->postJson(route('api.iot.cabinets.movements', $cabinet), array_merge(
        array_diff_key(cabinetMovementPayload(), ['rpi_employee_id' => true]),
        ['employee_id' => $otherEmployee->id],
    ))->assertUnprocessable()->assertJsonValidationErrors('payload');
    $this->postJson(route('api.iot.cabinets.movements', $cabinet), cabinetMovementPayload(['employee_name' => $otherEmployee->name]))
        ->assertUnprocessable()->assertJsonValidationErrors('payload');
    $this->postJson(route('api.iot.cabinets.movements', $cabinet), cabinetMovementPayload(['emp_name' => $otherEmployee->name]))
        ->assertUnprocessable()->assertJsonValidationErrors('payload');
    $this->assertDatabaseEmpty('file_movement_events');
    expect($employee->id)->not->toBe($otherEmployee->id);
});

it('rejects reusing an event ID for a different movement', function () {
    $cabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'device-token')]);
    mappedMovementEmployee();
    $payload = cabinetMovementPayload();

    $this->withHeader('X-Device-Token', 'device-token')
        ->postJson(route('api.iot.cabinets.movements', $cabinet), $payload)->assertCreated();
    $this->postJson(route('api.iot.cabinets.movements', $cabinet), cabinetMovementPayload(['action' => 'returned']))
        ->assertConflict();
    $this->assertDatabaseCount('file_movement_events', 1);
});

it('accepts a heartbeat and updates the cabinet connection state', function () {
    $cabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'device-token')]);

    $this->withHeader('X-Device-Token', 'device-token')
        ->postJson(route('api.iot.cabinets.heartbeat', $cabinet), [
            'door_state' => 'open',
            'drawer_reference' => 'A-1',
            'drawer_state' => 'open',
            'component_health' => [
                'facelock_service' => 'healthy',
                'arduino' => 'connected',
                'camera' => 'connected',
                'folder_rfid' => 'connected',
            ],
        ])->assertOk()->assertJsonPath('status', 'online')->assertJsonPath('state.door', 'open')
        ->assertJsonPath('state.drawers.A-1', 'open');

    expect($cabinet->fresh()->connectionStatus())->toBe('online');

    expect($cabinet->fresh()->component_health)->toBe([
        'facelock_service' => 'healthy',
        'arduino' => 'connected',
        'camera' => 'connected',
        'folder_rfid' => 'connected',
    ]);

    $this->postJson(route('api.iot.cabinets.heartbeat', $cabinet), ['door_state' => 'closed'])
        ->assertOk()->assertJsonPath('state.drawers.A-1', 'open');

    $this->assertDatabaseEmpty('file_movement_events');
});

it('provisions a cabinet token and refuses accidental replacement', function () {
    $this->artisan('cabinet:provision', ['identifier' => 'CAB-01', '--name' => 'Records cabinet'])->assertSuccessful();

    $cabinet = CabinetDevice::query()->where('identifier', 'CAB-01')->firstOrFail();
    expect($cabinet->name)->toBe('Records cabinet')
        ->and($cabinet->api_token_hash)->toHaveLength(64);
    $this->artisan('cabinet:provision', ['identifier' => 'CAB-01'])->assertFailed();
    $this->artisan('cabinet:provision', ['identifier' => 'CAB-01', '--rotate' => true])->assertSuccessful();
    expect($cabinet->fresh()->api_token_hash)->not->toBe($cabinet->api_token_hash);
    $this->assertDatabaseCount('cabinet_devices', 1);
});

<?php

use App\Models\CabinetDevice;
use App\Models\EmployeeCabinetAccess;
use App\Models\User;

function accessEventPayload(array $overrides = []): array
{
    return array_merge([
        'event_id' => 'access-001',
        'rpi_employee_id' => 'EMP001',
        'result' => 'granted',
        'occurred_at' => '2026-09-24T08:00:00+08:00',
        'authentication_method' => 'rfid_and_face',
        'authentication_verified' => true,
    ], $overrides);
}

function accessEventEmployee(): User
{
    $employee = User::factory()->create(['role' => 'staff', 'name' => 'Cabinet Employee']);
    EmployeeCabinetAccess::factory()->create([
        'user_id' => $employee->id,
        'rpi_employee_id' => 'EMP001',
        'is_active' => true,
        'rfid_enrollment_status' => EmployeeCabinetAccess::ENROLLED,
        'face_enrollment_status' => EmployeeCabinetAccess::ENROLLED,
    ]);

    return $employee;
}

it('records granted access for the mapped employee without claiming the door opened', function () {
    $cabinet = CabinetDevice::factory()->create(['identifier' => 'CAB-01', 'api_token_hash' => hash('sha256', 'device-token')]);
    $employee = accessEventEmployee();

    $this->withHeader('X-Device-Token', 'device-token')
        ->postJson(route('api.iot.cabinets.access-events', $cabinet), accessEventPayload(['rpi_employee_id' => ' emp001 ']))
        ->assertCreated()->assertJsonPath('duplicate', false);

    $this->assertDatabaseHas('administrative_audits', [
        'device_event_id' => $cabinet->id.':access-001',
        'user_id' => $employee->id,
        'actor' => 'Cabinet Employee',
        'cabinet_identifier' => 'CAB-01',
        'rpi_employee_id' => 'EMP001',
        'access_result' => 'granted',
        'authentication_method' => 'rfid_and_face',
    ]);

    $this->actingAs(User::factory()->superAdmin()->create())->getJson(route('admin.audit.index'))
        ->assertOk()->assertJsonPath('events.0.action', 'Cabinet access granted')
        ->assertJsonPath('events.0.type', 'cabinet')
        ->assertJsonPath('events.0.time', 'Sep 24, 2026 08:00')
        ->assertSee('Opening not confirmed')->assertDontSee('Door sensor reported open');
});

it('records denied attempts even when the device employee ID has no mapping', function () {
    $cabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'device-token')]);
    $this->withHeader('X-Device-Token', 'device-token')
        ->postJson(route('api.iot.cabinets.access-events', $cabinet), [
            'event_id' => 'denied-001', 'rpi_employee_id' => 'EMP999',
            'result' => 'denied', 'occurred_at' => '2026-09-24T08:00:00+08:00',
        ])->assertCreated();

    $this->assertDatabaseHas('administrative_audits', [
        'user_id' => null, 'actor' => 'Unmapped employee', 'rpi_employee_id' => 'EMP999',
        'access_result' => 'denied', 'authentication_method' => null,
    ]);
});

it('does not invent authentication details omitted by the device', function () {
    $cabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'device-token')]);
    accessEventEmployee();

    $this->withHeader('X-Device-Token', 'device-token')
        ->postJson(route('api.iot.cabinets.access-events', $cabinet), [
            'event_id' => 'grant-no-method', 'rpi_employee_id' => 'EMP001',
            'result' => 'granted', 'occurred_at' => '2026-09-24T08:00:00+08:00',
        ])->assertCreated();

    $this->actingAs(User::factory()->superAdmin()->create())->getJson(route('admin.audit.index'))
        ->assertJsonPath('events.0.action', 'Cabinet access granted')
        ->assertDontSee('verified authentication');
    $this->assertDatabaseHas('administrative_audits', [
        'device_event_id' => $cabinet->id.':grant-no-method', 'authentication_method' => null,
    ]);
});

it('deduplicates retries and rejects changed events under the same device event ID', function () {
    $cabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'device-token')]);
    accessEventEmployee();
    $payload = accessEventPayload();

    $this->withHeader('X-Device-Token', 'device-token')
        ->postJson(route('api.iot.cabinets.access-events', $cabinet), $payload)->assertCreated();
    $this->postJson(route('api.iot.cabinets.access-events', $cabinet), $payload)
        ->assertOk()->assertJsonPath('duplicate', true);
    $this->postJson(route('api.iot.cabinets.access-events', $cabinet), accessEventPayload(['result' => 'opened', 'door_sensor_state' => 'open']))
        ->assertConflict();
    $this->assertDatabaseCount('administrative_audits', 1);
});

it('requires an authenticated device and rejects secrets in access payloads', function () {
    $cabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'device-token')]);
    accessEventEmployee();
    $this->postJson(route('api.iot.cabinets.access-events', $cabinet), accessEventPayload())->assertUnauthorized();
    $this->withHeader('X-Device-Token', 'wrong-token')
        ->postJson(route('api.iot.cabinets.access-events', $cabinet), accessEventPayload())->assertUnauthorized();
    $this->withHeader('X-Device-Token', 'device-token')
        ->postJson(route('api.iot.cabinets.access-events', $cabinet), accessEventPayload(['biometric_template' => 'secret']))
        ->assertUnprocessable()->assertJsonValidationErrors('payload');
    $this->assertDatabaseEmpty('administrative_audits');
});

it('requires effective mapping for grants and an open sensor report for physical opening', function () {
    $cabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'device-token')]);
    $this->withHeader('X-Device-Token', 'device-token')
        ->postJson(route('api.iot.cabinets.access-events', $cabinet), accessEventPayload())
        ->assertUnprocessable()->assertJsonValidationErrors('rpi_employee_id');
    accessEventEmployee();
    $this->postJson(route('api.iot.cabinets.access-events', $cabinet), accessEventPayload(['result' => 'opened']))
        ->assertUnprocessable()->assertJsonValidationErrors('door_sensor_state');
    $this->postJson(route('api.iot.cabinets.access-events', $cabinet), accessEventPayload([
        'result' => 'opened', 'door_sensor_state' => 'open',
    ]))->assertCreated();

    $this->actingAs(User::factory()->superAdmin()->create())->getJson(route('admin.audit.index'))
        ->assertJsonPath('events.0.action', 'Cabinet door opened (sensor confirmed)')
        ->assertSee('Door sensor reported open');
});

it('retains a sensor-confirmed opening even when no employee is mapped', function () {
    $cabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'device-token')]);

    $this->withHeader('X-Device-Token', 'device-token')
        ->postJson(route('api.iot.cabinets.access-events', $cabinet), [
            'event_id' => 'open-unknown', 'result' => 'opened', 'door_sensor_state' => 'open',
            'occurred_at' => '2026-09-24T08:00:00+08:00',
        ])->assertCreated();

    $this->assertDatabaseHas('administrative_audits', [
        'device_event_id' => $cabinet->id.':open-unknown',
        'access_result' => 'opened', 'user_id' => null, 'authentication_method' => null,
    ]);
});

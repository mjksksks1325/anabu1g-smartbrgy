<?php

use App\Models\CabinetCredentialRevocation;
use App\Models\CabinetDevice;
use App\Models\EmployeeCabinetAccess;
use App\Models\User;
use Illuminate\Support\Str;

it('delivers employee face and RFID revocations until each cabinet acknowledges them', function () {
    $admin = User::factory()->superAdmin()->create(['role' => 'admin']);
    $employee = User::factory()->create(['role' => 'staff']);
    $access = EmployeeCabinetAccess::factory()->create([
        'user_id' => $employee->id,
        'rpi_employee_id' => 'EMP022',
        'is_active' => true,
        'rfid_enrollment_status' => EmployeeCabinetAccess::ENROLLED,
        'face_enrollment_status' => EmployeeCabinetAccess::ENROLLED,
    ]);
    $firstCabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'first-token')]);
    $secondCabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'second-token')]);

    $this->actingAs($admin)->deleteJson(route('admin.users.destroy', $employee))->assertOk();
    $firstCommand = CabinetCredentialRevocation::query()->where('cabinet_device_id', $firstCabinet->id)->firstOrFail();
    $secondCommand = CabinetCredentialRevocation::query()->where('cabinet_device_id', $secondCabinet->id)->firstOrFail();

    $this->withHeader('X-Device-Token', 'first-token')
        ->getJson(route('api.iot.cabinets.employee-deprovisionments.pending', $firstCabinet))
        ->assertExactJson(['revocations' => [[
            'request_id' => $firstCommand->request_id,
            'rpi_employee_id' => 'EMP022',
            'remove_rfid' => true,
            'remove_face' => true,
            'requested_at' => $firstCommand->created_at->toIso8601String(),
        ]]]);

    $this->withHeader('X-Device-Token', 'second-token')
        ->postJson(route('api.iot.cabinets.employee-deprovisionments.acknowledge', [$secondCabinet, $firstCommand->request_id]))
        ->assertNotFound();

    $this->withHeader('X-Device-Token', 'first-token')
        ->postJson(route('api.iot.cabinets.employee-deprovisionments.acknowledge', [$firstCabinet, $firstCommand->request_id]))
        ->assertOk()->assertJsonPath('status', 'acknowledged');

    expect($firstCommand->fresh()->acknowledged_at)->not->toBeNull()
        ->and($access->fresh()->rpi_employee_id)->toBe('EMP022');

    $this->withHeader('X-Device-Token', 'second-token')
        ->postJson(route('api.iot.cabinets.employee-deprovisionments.acknowledge', [$secondCabinet, $secondCommand->request_id]))
        ->assertOk()->assertJsonPath('status', 'acknowledged');

    expect($secondCommand->fresh()->acknowledged_at)->not->toBeNull()
        ->and($access->fresh()->rpi_employee_id)->toBeNull()
        ->and($access->fresh()->rfid_enrollment_status)->toBe(EmployeeCabinetAccess::NOT_STARTED)
        ->and($access->fresh()->face_enrollment_status)->toBe(EmployeeCabinetAccess::NOT_STARTED);

    $this->withHeader('X-Device-Token', 'first-token')
        ->getJson(route('api.iot.cabinets.employee-deprovisionments.pending', $firstCabinet))
        ->assertExactJson(['revocations' => []]);
});

it('requires an authenticated cabinet device to poll and acknowledge credential revocations', function () {
    $cabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'device-token')]);
    $revocation = CabinetCredentialRevocation::query()->create([
        'request_id' => (string) Str::uuid(),
        'cabinet_device_id' => $cabinet->id,
        'rpi_employee_id' => 'EMP032',
    ]);

    $this->getJson(route('api.iot.cabinets.employee-deprovisionments.pending', $cabinet))->assertUnauthorized();
    $this->withHeader('X-Device-Token', 'wrong-token')
        ->postJson(route('api.iot.cabinets.employee-deprovisionments.acknowledge', [$cabinet, $revocation->request_id]))
        ->assertUnauthorized();

    expect($revocation->fresh()->acknowledged_at)->toBeNull();
});

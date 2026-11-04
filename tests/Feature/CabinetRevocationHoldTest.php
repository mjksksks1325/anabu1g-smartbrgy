<?php

use App\Models\CabinetCredentialRevocation;
use App\Models\CabinetDevice;
use App\Models\EmployeeCabinetAccess;
use App\Models\User;
use Illuminate\Support\Str;

it('withholds a paused deletion and rejects its acknowledgment without clearing employee credentials', function () {
    $cabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'test-token')]);
    $employee = User::factory()->create();
    $access = EmployeeCabinetAccess::factory()->create(['user_id' => $employee->id, 'rpi_employee_id' => 'EMP006', 'rfid_enrollment_status' => EmployeeCabinetAccess::ENROLLED, 'face_enrollment_status' => EmployeeCabinetAccess::ENROLLED]);
    $employee->delete();
    $request = CabinetCredentialRevocation::query()->create(['request_id' => (string) Str::uuid(), 'cabinet_device_id' => $cabinet->id, 'rpi_employee_id' => 'EMP006', 'remove_rfid' => true, 'remove_face' => true]);
    $request->forceFill(['paused_at' => now()])->save();

    $this->withHeader('X-Device-Token', 'test-token')->getJson(route('api.iot.cabinets.employee-deprovisionments.pending', $cabinet))->assertOk()->assertExactJson(['revocations' => []]);
    $this->postJson(route('api.iot.cabinets.employee-deprovisionments.acknowledge', [$cabinet, $request->request_id]))->assertConflict();
    expect($request->fresh())->acknowledged_at->toBeNull()->remove_rfid->toBeTrue()->remove_face->toBeTrue();
    expect($access->fresh())->rpi_employee_id->toBe('EMP006')->rfid_enrollment_status->toBe(EmployeeCabinetAccess::ENROLLED)->face_enrollment_status->toBe(EmployeeCabinetAccess::ENROLLED);

    $request->forceFill(['paused_at' => null])->save();
    $this->getJson(route('api.iot.cabinets.employee-deprovisionments.pending', $cabinet))->assertJsonCount(1, 'revocations')->assertJsonPath('revocations.0.request_id', $request->request_id);
    $this->postJson(route('api.iot.cabinets.employee-deprovisionments.acknowledge', [$cabinet, $request->request_id]))->assertOk();
    expect($request->fresh()->acknowledged_at)->not->toBeNull();
    expect($access->fresh()->rpi_employee_id)->toBeNull();
});

it('delivers normal revocations while preserving a separate held request', function () {
    $cabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'test-token')]);
    $paused = CabinetCredentialRevocation::query()->create(['request_id' => (string) Str::uuid(), 'cabinet_device_id' => $cabinet->id, 'rpi_employee_id' => 'EMP006']);
    $paused->forceFill(['paused_at' => now()])->save();
    $active = CabinetCredentialRevocation::query()->create(['request_id' => (string) Str::uuid(), 'cabinet_device_id' => $cabinet->id, 'rpi_employee_id' => 'EMP009']);

    $this->withHeader('X-Device-Token', 'test-token')->getJson(route('api.iot.cabinets.employee-deprovisionments.pending', $cabinet))->assertOk()
        ->assertJsonCount(1, 'revocations')->assertJsonPath('revocations.0.request_id', $active->request_id);
    expect($paused->fresh())->paused_at->not->toBeNull()->acknowledged_at->toBeNull();
});

it('refuses rollback that would accidentally release a paused removal', function () {
    $cabinet = CabinetDevice::factory()->create();
    $request = CabinetCredentialRevocation::query()->create(['request_id' => (string) Str::uuid(), 'cabinet_device_id' => $cabinet->id, 'rpi_employee_id' => 'EMP006']);
    $request->forceFill(['paused_at' => now()])->save();
    $migration = require database_path('migrations/2026_11_04_214303_add_paused_at_to_cabinet_credential_revocations_table.php');
    expect(fn () => $migration->down())->toThrow(LogicException::class);
    expect($request->fresh()->paused_at)->not->toBeNull();
});

<?php

use App\Models\EmployeeCabinetAccess;
use Illuminate\Database\QueryException;

it('stores a stable RPi employee ID on a cabinet access record', function () {
    $access = EmployeeCabinetAccess::factory()->create(['rpi_employee_id' => 'EMP001']);

    expect($access->fresh()->rpi_employee_id)->toBe('EMP001');
    $this->assertDatabaseHas('employee_cabinet_access', [
        'id' => $access->id,
        'rpi_employee_id' => 'EMP001',
    ]);
});

it('does not allow two cabinet access records to share a non-null RPi employee ID', function () {
    EmployeeCabinetAccess::factory()->create(['rpi_employee_id' => 'EMP002']);

    expect(fn () => EmployeeCabinetAccess::factory()->create(['rpi_employee_id' => 'EMP002']))
        ->toThrow(QueryException::class);
    $this->assertDatabaseCount('employee_cabinet_access', 1);
});

it('allows unmapped cabinet access records to retain a null RPi employee ID', function () {
    $accessRecords = EmployeeCabinetAccess::factory()->count(2)->create();

    expect($accessRecords->pluck('rpi_employee_id')->all())->toBe([null, null]);
    $this->assertDatabaseCount('employee_cabinet_access', 2);
});

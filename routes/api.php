<?php

use App\Http\Controllers\Api\CabinetEventController;
use App\Http\Middleware\AuthenticateCabinetDevice;
use Illuminate\Support\Facades\Route;

Route::prefix('iot/cabinets/{cabinet:identifier}')
    ->middleware([AuthenticateCabinetDevice::class, 'throttle:120,1'])
    ->group(function (): void {
        Route::post('/heartbeat', [CabinetEventController::class, 'heartbeat'])->name('api.iot.cabinets.heartbeat');
        Route::post('/movements', [CabinetEventController::class, 'movement'])->name('api.iot.cabinets.movements');

        Route::get('/employee-enrollment/pending', [CabinetEventController::class, 'pendingEmployeeEnrollment'])
            ->name('api.iot.cabinets.employee-enrollment.pending');

        Route::post('/employee-enrollment', [CabinetEventController::class, 'employeeEnrollment'])
            ->name('api.iot.cabinets.employee-enrollment');
    });

<?php

use App\Http\Controllers\Api\CabinetAccessEventController;
use App\Http\Controllers\Api\CabinetCredentialRevocationController;
use App\Http\Controllers\Api\CabinetEventController;
use App\Http\Controllers\Api\CabinetFileEnrollmentController;
use App\Http\Middleware\AuthenticateCabinetDevice;
use Illuminate\Support\Facades\Route;

Route::prefix('iot/cabinets/{cabinet:identifier}')
    ->middleware([AuthenticateCabinetDevice::class, 'throttle:cabinet-device'])
    ->group(function (): void {
        Route::post('/heartbeat', [CabinetEventController::class, 'heartbeat'])->name('api.iot.cabinets.heartbeat');
        Route::post('/movements', [CabinetEventController::class, 'movement'])->name('api.iot.cabinets.movements');
        Route::post('/access-events', CabinetAccessEventController::class)->name('api.iot.cabinets.access-events');

        Route::get('/employee-enrollment/pending', [CabinetEventController::class, 'pendingEmployeeEnrollment'])
            ->name('api.iot.cabinets.employee-enrollment.pending');

        Route::get('/access-authorizations', [CabinetEventController::class, 'accessAuthorizations'])
            ->name('api.iot.cabinets.access-authorizations');

        Route::post('/employee-enrollment', [CabinetEventController::class, 'employeeEnrollment'])
            ->name('api.iot.cabinets.employee-enrollment');

        Route::get('/employee-deprovisionments/pending', [CabinetCredentialRevocationController::class, 'pending'])
            ->name('api.iot.cabinets.employee-deprovisionments.pending');
        Route::post('/employee-deprovisionments/{requestId}/acknowledge', [CabinetCredentialRevocationController::class, 'acknowledge'])
            ->whereUuid('requestId')
            ->name('api.iot.cabinets.employee-deprovisionments.acknowledge');

        Route::get('/folder-enrollment/pending', [CabinetFileEnrollmentController::class, 'pending'])
            ->name('api.iot.cabinets.folder-enrollment.pending');
        Route::get('/folder-registry', [CabinetFileEnrollmentController::class, 'registry'])
            ->name('api.iot.cabinets.folder-registry');
        Route::post('/folder-enrollment/{requestId}/confirm', [CabinetFileEnrollmentController::class, 'confirm'])
            ->whereUuid('requestId')
            ->name('api.iot.cabinets.folder-enrollment.confirm');
    });

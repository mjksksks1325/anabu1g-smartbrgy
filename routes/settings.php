<?php

use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Profile;
use App\Livewire\Settings\Security;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\ConfirmablePasswordController;

Route::middleware(['auth:web', 'can:access-employee-settings'])->prefix('staff')->group(function () {
    Route::redirect('settings', '/staff/settings/profile');
    Route::post('settings/confirm-password', [ConfirmablePasswordController::class, 'store'])
        ->middleware('throttle:6,1')->name('staff.password.confirm');

    Route::livewire('settings/profile', Profile::class)->name('profile.edit');
});

Route::middleware(['auth:web', 'verified', 'can:access-employee-settings'])->prefix('staff')->group(function () {
    Route::livewire('settings/appearance', Appearance::class)->name('appearance.edit');

    Route::livewire('settings/security', Security::class)
        ->middleware([
            'password.confirm',
        ])
        ->name('security.edit');
});

Route::middleware(['auth:web', 'can:access-employee-settings'])->group(function () {
    Route::redirect('/settings', '/staff/settings/profile');
    Route::redirect('/settings/profile', '/staff/settings/profile');
    Route::redirect('/settings/appearance', '/staff/settings/appearance');
    Route::redirect('/settings/security', '/staff/settings/security');
});

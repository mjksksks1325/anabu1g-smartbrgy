<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BarangayProtectionOrderController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DocumentRequestController as AdminDocumentRequestController;
use App\Http\Controllers\Admin\EmployeeCabinetAccessController;
use App\Http\Controllers\Admin\HouseholdController;
use App\Http\Controllers\Admin\HouseholdProfilingImportController;
use App\Http\Controllers\Admin\IncidentController;
use App\Http\Controllers\Admin\IssuedCertificateController;
use App\Http\Controllers\Admin\PurokController;
use App\Http\Controllers\Admin\ResidentController;
use App\Http\Controllers\Admin\ResidentPhotoController;
use App\Http\Controllers\Admin\ResidentPortalAccountController;
use App\Http\Controllers\Admin\ResidentRequestRestrictionController;
use App\Http\Controllers\Admin\RfidFileTrackingController;
use App\Http\Controllers\Admin\SmartCabinetController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VoterRegistrationController;
use App\Http\Controllers\Admin\WorkspaceController;
use App\Http\Controllers\CertificateVerificationController;
use App\Http\Controllers\DocumentRequestController;
use App\Http\Controllers\EmployeeSessionController;
use App\Http\Controllers\ResidentPortalController;
use App\Http\Controllers\ResidentProfilePhotoController;
use App\Http\Controllers\ResidentRegistrationController;
use App\Http\Controllers\ResidentSessionController;
use App\Http\Middleware\EnsureGuestResidentPortal;
use App\Http\Middleware\EnsureResidentAccount;
use App\Http\Middleware\RecordAdministrativeAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/portal');
});

Route::get('/portal', [ResidentPortalController::class, 'index'])->name('home');
Route::get('/portal/request', [ResidentPortalController::class, 'createRequest'])
    ->middleware(EnsureResidentAccount::class)
    ->name('portal.request.create');

Route::get('/dashboard', function (): RedirectResponse {
    return redirect()->route(auth()->user()->isResidentAccount() ? 'portal.account' : 'admin.dashboard');
})->middleware('auth')->name('dashboard');

Route::post('/portal/request', [DocumentRequestController::class, 'store'])
    ->middleware([EnsureResidentAccount::class, 'throttle:10,1'])
    ->name('portal.request.store');

Route::get('/portal/csrf-token', [DocumentRequestController::class, 'csrfToken'])
    ->name('portal.csrf-token');

Route::get('/portal/request/{referenceCode}', [DocumentRequestController::class, 'status'])
    ->middleware(['auth:resident', 'throttle:60,1'])
    ->name('portal.request.status');

Route::get('/portal/information', [ResidentPortalController::class, 'information'])->name('portal.information');
Route::get('/portal/officials', [ResidentPortalController::class, 'officials'])->name('portal.officials');
Route::middleware(EnsureGuestResidentPortal::class)->group(function () {
    Route::get('/portal/login', [ResidentPortalController::class, 'login'])->name('portal.login');
    Route::post('/portal/login', [ResidentSessionController::class, 'store'])->middleware('throttle:resident-login')->name('portal.login.store');
    Route::get('/portal/register', [ResidentRegistrationController::class, 'create'])->name('portal.register');
    Route::post('/portal/register/reset', [ResidentRegistrationController::class, 'reset'])->name('portal.register.reset');
    Route::post('/portal/register/name', [ResidentRegistrationController::class, 'verifyName'])->middleware('throttle:resident-registration')->name('portal.register.name');
    Route::post('/portal/register/confirm-record', [ResidentRegistrationController::class, 'confirmRecord'])->middleware('throttle:resident-registration')->name('portal.register.confirm-record');
    Route::post('/portal/register/send-code', [ResidentRegistrationController::class, 'sendCode'])->middleware('throttle:resident-registration')->name('portal.register.send-code');
    Route::post('/portal/register/verify', [ResidentRegistrationController::class, 'verify'])->middleware('throttle:resident-registration')->name('portal.register.verify');
    Route::post('/portal/register', [ResidentRegistrationController::class, 'store'])->middleware('throttle:resident-registration')->name('portal.register.store');
    Route::view('/portal/registration-help', 'portal.registration-help')->name('portal.registration.denied');
});
Route::post('/portal/logout', [ResidentSessionController::class, 'destroy'])->middleware('auth:resident')->name('portal.logout');
Route::post('/logout', [EmployeeSessionController::class, 'destroy'])->middleware('auth:web')->name('logout');
Route::middleware(EnsureResidentAccount::class)->group(function () {
    Route::get('/portal/account', [ResidentPortalController::class, 'account'])->name('portal.account');
    Route::get('/portal/account/statuses', [ResidentPortalController::class, 'requestStatuses'])
        ->middleware('throttle:30,1')
        ->name('portal.account.statuses');
    Route::get('/portal/profile', [ResidentPortalController::class, 'profile'])->name('portal.profile');
    Route::get('/portal/profile/photo', [ResidentProfilePhotoController::class, 'show'])->name('portal.profile.photo');
    Route::post('/portal/profile/photo', [ResidentProfilePhotoController::class, 'store'])->name('portal.profile.photo.store');
    Route::get('/portal/identity', [ResidentPortalController::class, 'identity'])->name('portal.identity');
});

Route::middleware(['auth', RecordAdministrativeAction::class])->prefix('admin')->name('admin.')->group(function () {

    Route::get('/', WorkspaceController::class)->defaults('screen', 'dashboard')->name('dashboard');
    Route::get('/demographics', WorkspaceController::class)->defaults('screen', 'demographics')->name('demographics');
    Route::get('/voters', WorkspaceController::class)->defaults('screen', 'voters')->name('voters');
    Route::get('/request-eligibility', WorkspaceController::class)->defaults('screen', 'request-records')->name('request-eligibility');

    Route::get('/rfid-file-tracking', RfidFileTrackingController::class)->middleware('can:view-rfid-files')->name('rfid-files.index');
    Route::middleware('can:view-administration')->group(function () {
        Route::get('/audit', WorkspaceController::class)->defaults('screen', 'audit')->name('audit');
        Route::get('/settings', WorkspaceController::class)->defaults('screen', 'settings')->name('settings');
        Route::get('/smart-cabinet', SmartCabinetController::class)->name('smart-cabinet.index');
        Route::get('/employee-cabinet-access', [EmployeeCabinetAccessController::class, 'index'])->name('cabinet-access.index');
        Route::patch('/employee-cabinet-access/{user}', [EmployeeCabinetAccessController::class, 'update'])->name('cabinet-access.update');
        Route::patch('/employee-cabinet-access/{user}/rpi-employee-id', [EmployeeCabinetAccessController::class, 'updateRpiEmployeeId'])->name('cabinet-access.rpi-employee-id.update');
        Route::post('/employee-cabinet-access/{user}/enrollment/{method}', [EmployeeCabinetAccessController::class, 'enroll'])->name('cabinet-access.enroll');
        Route::get('/audit-log', AuditLogController::class)->name('audit.index');
        Route::get('/users', WorkspaceController::class)->defaults('screen', 'users')->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('/residents/{resident}/portal-account', [ResidentPortalAccountController::class, 'update'])->name('residents.portal-account');
    });

    Route::get('/document-requests-live', [AdminDocumentRequestController::class, 'live'])
        ->name('document-requests.live');
    Route::get('/dashboard-summary', DashboardController::class)->name('dashboard.summary');

    Route::get('/incidents', WorkspaceController::class)->defaults('screen', 'incidents')->name('incidents.index');
    Route::post('/incidents', [IncidentController::class, 'store'])->name('incidents.store');
    Route::get('/incident-options', [IncidentController::class, 'options'])->name('incidents.options');
    Route::get('/protection-orders', [BarangayProtectionOrderController::class, 'index'])->name('protection-orders.index');
    Route::post('/protection-orders', [BarangayProtectionOrderController::class, 'store'])->name('protection-orders.store');
    Route::get('/protection-orders/{protectionOrder}', [BarangayProtectionOrderController::class, 'show'])->name('protection-orders.show');
    Route::patch('/protection-orders/{protectionOrder}', [BarangayProtectionOrderController::class, 'update'])->name('protection-orders.update');
    Route::get('/request-restrictions', [ResidentRequestRestrictionController::class, 'index'])->name('request-restrictions.index');
    Route::post('/request-restrictions', [ResidentRequestRestrictionController::class, 'store'])->name('request-restrictions.store');
    Route::post('/request-restrictions/{restriction}/review', [ResidentRequestRestrictionController::class, 'review'])->name('request-restrictions.review');
    Route::post('/request-restrictions/{restriction}/lift', [ResidentRequestRestrictionController::class, 'lift'])->name('request-restrictions.lift');
    Route::get('/incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
    Route::patch('/incidents/{incident}', [IncidentController::class, 'update'])->name('incidents.update');
    Route::delete('/incidents/{incident}', [IncidentController::class, 'destroy'])->name('incidents.destroy');
    Route::get('/incidents/{incident}/attachments/{attachment}', [IncidentController::class, 'attachment'])
        ->whereNumber('attachment')
        ->name('incidents.attachments.show');

    Route::post('/residents/{resident}/portal-activation', [ResidentPortalAccountController::class, 'store'])->name('residents.portal-activation');

    Route::get('/residents', WorkspaceController::class)->defaults('screen', 'records')->name('residents.index');
    Route::post('/resident-profiling-imports/preview', [HouseholdProfilingImportController::class, 'preview'])->name('resident-profiling-imports.preview');
    Route::post('/resident-profiling-imports/review', [HouseholdProfilingImportController::class, 'review'])->name('resident-profiling-imports.review');
    Route::post('/resident-profiling-imports', [HouseholdProfilingImportController::class, 'store'])->name('resident-profiling-imports.store');
    Route::get('/households', [HouseholdController::class, 'index'])->name('households.index');
    Route::post('/households', [HouseholdController::class, 'store'])->name('households.store');
    Route::get('/households/{household}', [HouseholdController::class, 'show'])->name('households.show');
    Route::patch('/households/{household}', [HouseholdController::class, 'update'])->name('households.update');
    Route::patch('/households/{household}/members/{resident}', [HouseholdController::class, 'member'])->name('households.members.update');
    Route::delete('/households/{household}/members/{resident}', [HouseholdController::class, 'removeMember'])->name('households.members.destroy');
    Route::get('/residents-export', [ResidentController::class, 'export'])->name('residents.export');
    Route::post('/residents', [ResidentController::class, 'store'])->name('residents.store');
    Route::get('/request-records', [ResidentController::class, 'requestRecords'])->name('request-records.index');
    Route::get('/request-records-export', [ResidentController::class, 'requestRecordsExport'])->name('request-records.export');
    Route::get('/residents/{resident}/photo', [ResidentPhotoController::class, 'show'])->name('residents.photo');
    Route::post('/residents/{resident}/photo', [ResidentPhotoController::class, 'store'])->name('residents.photo.store');
    Route::get('/residents/{resident}', [ResidentController::class, 'show'])->name('residents.show');
    Route::get('/residents/{resident}/eligibility', [ResidentController::class, 'eligibility'])->name('residents.eligibility');
    Route::patch('/residents/{resident}', [ResidentController::class, 'update'])->name('residents.update');
    Route::delete('/residents/{resident}', [ResidentController::class, 'destroy'])->name('residents.destroy');
    Route::patch('/residents/{resident}/restore', [ResidentController::class, 'restore'])->name('residents.restore');

    Route::get('/puroks', [PurokController::class, 'index'])->name('puroks.index');
    Route::post('/puroks', [PurokController::class, 'store'])->name('puroks.store');

    Route::get('/voter-registrations', [VoterRegistrationController::class, 'index'])->name('voter-registrations.index');
    Route::get('/voter-registrations-export', [VoterRegistrationController::class, 'export'])->name('voter-registrations.export');
    Route::post('/voter-registrations', [VoterRegistrationController::class, 'store'])->name('voter-registrations.store');
    Route::get('/voter-registrations-template', [VoterRegistrationController::class, 'template'])->name('voter-registrations.template');
    Route::post('/voter-registrations-import', [VoterRegistrationController::class, 'import'])->name('voter-registrations.import');
    Route::patch('/voter-registrations/{voterRegistration}', [VoterRegistrationController::class, 'update'])->name('voter-registrations.update');

    Route::get('/document-requests', WorkspaceController::class)->defaults('screen', 'certificates')
        ->name('document-requests.index');
    Route::get('/document-requests/list', [AdminDocumentRequestController::class, 'index'])->name('document-requests.list');

    Route::get('/document-requests/{documentRequest}', [AdminDocumentRequestController::class, 'show'])
        ->name('document-requests.show');

    Route::get('/document-requests/{documentRequest}/attachment', [AdminDocumentRequestController::class, 'attachment'])
        ->name('document-requests.attachment');

    Route::patch('/document-requests/{documentRequest}/status', [AdminDocumentRequestController::class, 'updateStatus'])
        ->name('document-requests.update-status');

    Route::post('/issued-certificates', [IssuedCertificateController::class, 'store'])
        ->name('issued-certificates.store');

    Route::get('/issued-certificates', [IssuedCertificateController::class, 'index'])
        ->name('issued-certificates.index');

    Route::post('/document-requests/{documentRequest}/issue', [IssuedCertificateController::class, 'issueFromRequest']
    )->name('document-requests.issue');

    Route::get('/issued-certificates/{issuedCertificate}/photo', [IssuedCertificateController::class, 'photo'])->name('issued-certificates.photo');
    Route::get('/issued-certificates/{issuedCertificate}/print', [IssuedCertificateController::class, 'print']
    )->name('issued-certificates.print');

});

Route::get('/verify-certificate/{code}', [CertificateVerificationController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('certificate.verify');

require __DIR__.'/settings.php';

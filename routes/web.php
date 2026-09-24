<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DocumentRequestController as AdminDocumentRequestController;
use App\Http\Controllers\Admin\EmployeeCabinetAccessController;
use App\Http\Controllers\Admin\IncidentController;
use App\Http\Controllers\Admin\IssuedCertificateController;
use App\Http\Controllers\Admin\PurokController;
use App\Http\Controllers\Admin\ResidentController;
use App\Http\Controllers\Admin\ResidentPortalAccountController;
use App\Http\Controllers\Admin\RfidFileTrackingController;
use App\Http\Controllers\Admin\SmartCabinetController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VoterRegistrationController;
use App\Http\Controllers\CertificateVerificationController;
use App\Http\Controllers\DocumentRequestController;
use App\Http\Controllers\EmployeeSessionController;
use App\Http\Controllers\ResidentPortalController;
use App\Http\Controllers\ResidentRegistrationController;
use App\Http\Controllers\ResidentSessionController;
use App\Http\Middleware\EnsureGuestResidentPortal;
use App\Http\Middleware\EnsureResidentAccount;
use App\Http\Middleware\RecordAdministrativeAction;
use App\Models\DocumentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
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
Route::middleware(EnsureGuestResidentPortal::class)->group(function () {
    Route::get('/portal/login', [ResidentPortalController::class, 'login'])->name('portal.login');
    Route::post('/portal/login', [ResidentSessionController::class, 'store'])->middleware('throttle:resident-login')->name('portal.login.store');
    Route::get('/portal/register', [ResidentRegistrationController::class, 'create'])->name('portal.register');
    Route::post('/portal/register/verify', [ResidentRegistrationController::class, 'verify'])->middleware('throttle:resident-registration')->name('portal.register.verify');
    Route::post('/portal/register', [ResidentRegistrationController::class, 'store'])->middleware('throttle:resident-registration')->name('portal.register.store');
    Route::view('/portal/registration-help', 'portal.registration-help')->name('portal.registration.denied');
});
Route::post('/portal/logout', [ResidentSessionController::class, 'destroy'])->middleware('auth:resident')->name('portal.logout');
Route::post('/logout', [EmployeeSessionController::class, 'destroy'])->middleware('auth:web')->name('logout');
Route::middleware(EnsureResidentAccount::class)->group(function () {
    Route::get('/portal/account', [ResidentPortalController::class, 'account'])->name('portal.account');
    Route::get('/portal/profile', [ResidentPortalController::class, 'profile'])->name('portal.profile');
    Route::get('/portal/identity', [ResidentPortalController::class, 'identity'])->name('portal.identity');
});

Route::middleware(['auth', RecordAdministrativeAction::class])->prefix('admin')->name('admin.')->group(function () {

    Route::get('/', function () {
        Gate::authorize('viewAny', DocumentRequest::class);
        $requests = DocumentRequest::latest()->get();

        return view('admin.dashboard', compact('requests'));
    })->name('dashboard');

    Route::get('/rfid-file-tracking', RfidFileTrackingController::class)->middleware('can:view-rfid-files')->name('rfid-files.index');
    Route::middleware('can:view-administration')->group(function () {
        Route::get('/smart-cabinet', SmartCabinetController::class)->name('smart-cabinet.index');
        Route::get('/employee-cabinet-access', [EmployeeCabinetAccessController::class, 'index'])->name('cabinet-access.index');
        Route::patch('/employee-cabinet-access/{user}', [EmployeeCabinetAccessController::class, 'update'])->name('cabinet-access.update');
        Route::patch('/employee-cabinet-access/{user}/rpi-employee-id', [EmployeeCabinetAccessController::class, 'updateRpiEmployeeId'])->name('cabinet-access.rpi-employee-id.update');
        Route::post('/employee-cabinet-access/{user}/enrollment/{method}', [EmployeeCabinetAccessController::class, 'enroll'])->name('cabinet-access.enroll');
        Route::get('/audit-log', AuditLogController::class)->name('audit.index');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('/residents/{resident}/portal-account', [ResidentPortalAccountController::class, 'update'])->name('residents.portal-account');
    });

    Route::get('/document-requests-live', [AdminDocumentRequestController::class, 'live'])
        ->name('document-requests.live');
    Route::get('/dashboard-summary', DashboardController::class)->name('dashboard.summary');

    Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::post('/incidents', [IncidentController::class, 'store'])->name('incidents.store');
    Route::get('/incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
    Route::patch('/incidents/{incident}', [IncidentController::class, 'update'])->name('incidents.update');
    Route::delete('/incidents/{incident}', [IncidentController::class, 'destroy'])->name('incidents.destroy');
    Route::get('/incidents/{incident}/attachments/{attachment}', [IncidentController::class, 'attachment'])
        ->whereNumber('attachment')
        ->name('incidents.attachments.show');

    Route::post('/residents/{resident}/portal-activation', [ResidentPortalAccountController::class, 'store'])->name('residents.portal-activation');

    Route::get('/residents', [ResidentController::class, 'index'])->name('residents.index');
    Route::get('/residents-export', [ResidentController::class, 'export'])->name('residents.export');
    Route::post('/residents', [ResidentController::class, 'store'])->name('residents.store');
    Route::get('/request-records', [ResidentController::class, 'requestRecords'])->name('request-records.index');
    Route::get('/request-records-export', [ResidentController::class, 'requestRecordsExport'])->name('request-records.export');
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
    Route::patch('/voter-registrations/{voterRegistration}', [VoterRegistrationController::class, 'update'])->name('voter-registrations.update');

    Route::get('/document-requests', [AdminDocumentRequestController::class, 'index'])
        ->name('document-requests.index');

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

    Route::get('/issued-certificates/{issuedCertificate}/print', [IssuedCertificateController::class, 'print']
    )->name('issued-certificates.print');

});

Route::get('/verify-certificate/{code}', [CertificateVerificationController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('certificate.verify');

require __DIR__.'/settings.php';

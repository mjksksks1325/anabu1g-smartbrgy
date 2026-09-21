<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DocumentRequestController as AdminDocumentRequestController;
use App\Http\Controllers\Admin\IncidentController;
use App\Http\Controllers\Admin\IssuedCertificateController;
use App\Http\Controllers\Admin\PurokController;
use App\Http\Controllers\Admin\ResidentController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VoterRegistrationController;
use App\Http\Controllers\CertificateVerificationController;
use App\Http\Controllers\DocumentRequestController;
use App\Http\Middleware\RecordAdministrativeAction;
use App\Models\DocumentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/portal');
});

Route::get('/portal', function () {
    return view('portal.index');
})->name('home');

Route::get('/dashboard', function (): RedirectResponse {
    return redirect()->route('admin.dashboard');
})->middleware('auth')->name('dashboard');

Route::post('/portal/request', [DocumentRequestController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('portal.request.store');

Route::get('/portal/csrf-token', [DocumentRequestController::class, 'csrfToken'])
    ->name('portal.csrf-token');

Route::get('/portal/request/{referenceCode}', [DocumentRequestController::class, 'status'])
    ->middleware('throttle:60,1')
    ->name('portal.request.status');

Route::middleware(['auth', RecordAdministrativeAction::class])->prefix('admin')->name('admin.')->group(function () {

    Route::get('/', function () {
        Gate::authorize('viewAny', DocumentRequest::class);
        $requests = DocumentRequest::latest()->get();

        return view('admin.dashboard', compact('requests'));
    })->name('dashboard');

    Route::get('/audit-log', AuditLogController::class)->name('audit.index');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');

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

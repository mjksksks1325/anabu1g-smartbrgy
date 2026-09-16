<?php

use App\Http\Controllers\Admin\DocumentRequestController as AdminDocumentRequestController;
use App\Http\Controllers\Admin\IssuedCertificateController;
use App\Http\Controllers\CertificateVerificationController;
use App\Http\Controllers\DocumentRequestController;
use App\Models\DocumentRequest;
use Illuminate\Http\RedirectResponse;
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
    ->name('portal.request.store');

Route::get('/portal/request/{referenceCode}', [DocumentRequestController::class, 'status'])
    ->name('portal.request.status');

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {

    Route::get('/', function () {
        $requests = DocumentRequest::latest()->get();

        return view('admin.dashboard', compact('requests'));
    })->name('dashboard');

    Route::get('/document-requests-live', [AdminDocumentRequestController::class, 'live'])
        ->name('document-requests.live');

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

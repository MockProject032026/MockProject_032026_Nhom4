<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Controllers
use App\Http\Controllers\Api\SignerVerificationController;
use App\Http\Controllers\Api\BiometricController;
use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\EntryDetailController;

use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\JournalController;
use App\Http\Controllers\NotaryJournal\LinkedActController;
use App\Http\Controllers\NotaryJournal\AuditLogController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | SC_004 + SC_005 + SC_006 (Journal Entry Core APIs)
    |--------------------------------------------------------------------------
    */
    Route::prefix('journal-entries/{id}')->group(function () {

        // SC_004: Entry Detail
        Route::get('/', [EntryDetailController::class, 'show']);

        // SC_005: Signer Verification
        Route::get('signer-verification',        [SignerVerificationController::class, 'show']);
        Route::put('signer-verification',        [SignerVerificationController::class, 'update']);
        Route::get('signer-verification/compliance', [SignerVerificationController::class, 'compliance']);
        Route::post('finalize',                  [SignerVerificationController::class, 'finalize']);

        // Attachments
        Route::get('attachments',                [AttachmentController::class, 'index']);
        Route::post('attachments',               [AttachmentController::class, 'store']);
        Route::delete('attachments/{attachmentId}', [AttachmentController::class, 'destroy']);

        // SC_006: Biometrics
        Route::get('biometrics',                 [BiometricController::class, 'show']);
        Route::post('biometrics',                [BiometricController::class, 'store']);
        Route::get('biometrics/metadata',        [BiometricController::class, 'metadata']);
        Route::delete('biometrics/{biometricId}', [BiometricController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | SC_007: Linked Notarial Act
    |--------------------------------------------------------------------------
    */
    Route::prefix('journal-entries/{journal_entry_id}/linked-act')->group(function () {
        Route::get('/',                     [LinkedActController::class, 'summary']);
        Route::get('certification',         [LinkedActController::class, 'certification']);
        Route::get('audit-trail',           [LinkedActController::class, 'auditTrail']);
        Route::get('verification-status',   [LinkedActController::class, 'verificationStatus']);
        Route::get('certificates',          [LinkedActController::class, 'certificates']);
        Route::post('signer-confirmation',  [LinkedActController::class, 'signerConfirmation']);
        Route::post('export',               [LinkedActController::class, 'export']);
    });

    /*
    |--------------------------------------------------------------------------
    | SC_008: Audit Logs
    |--------------------------------------------------------------------------
    */
    Route::get('audit-logs/kpi-summary',   [AuditLogController::class, 'kpiSummary']);
    Route::get('audit-logs',               [AuditLogController::class, 'index']);
    Route::get('audit-logs/{log_id}',      [AuditLogController::class, 'show']);
    Route::post('audit-logs/export',       [AuditLogController::class, 'export']);

    /*
    |--------------------------------------------------------------------------
    | Dashboard (Admin / Compliance)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['auth', 'checkRole:1,2'])->group(function () {
        Route::get('dashboard/kpi-summary',        [DashboardController::class, 'kpiSummary']);
        Route::get('dashboard/compliance-logs',    [DashboardController::class, 'complianceLogs']);
        Route::get('dashboard/audit-logs/{id}',    [DashboardController::class, 'auditLogDetail']);
        Route::post('notaries/reminders/missing-signatures',
                                                    [DashboardController::class, 'sendMissingSignatureReminders']);
    });

    /*
    |--------------------------------------------------------------------------
    | Journal APIs
    |--------------------------------------------------------------------------
    */
    Route::get('journals',                  [JournalController::class, 'index']);
    Route::get('journals/{id}',             [JournalController::class, 'show']);
    Route::patch('journals/{id}/waive-thumbprint',
                                                [JournalController::class, 'waiveThumbprint']);

    /*
    |--------------------------------------------------------------------------
    | Notary Biometrics
    |--------------------------------------------------------------------------
    */
    Route::get('notaries/{notaryId}/biometrics',    [BiometricController::class, 'notaryBiometrics']);
    Route::post('notaries/{notaryId}/biometrics',   [BiometricController::class, 'storeNotaryBiometrics']);
    Route::delete('notaries/{notaryId}/biometrics', [BiometricController::class, 'destroyNotaryBiometrics']);
});

/*
|--------------------------------------------------------------------------
| Auth
|--------------------------------------------------------------------------
*/
Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NotaryJournal\LinkedActController;
use App\Http\Controllers\NotaryJournal\AuditLogController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\JournalController;



Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

Route::group([], function () {

    $base = 'journal-entries/{journal_entry_id}/linked-act';

    Route::get($base,                           [LinkedActController::class, 'summary']);
    Route::get($base . '/certification',        [LinkedActController::class, 'certification']);
    Route::get($base . '/audit-trail',          [LinkedActController::class, 'auditTrail']);
    Route::get($base . '/verification-status',  [LinkedActController::class, 'verificationStatus']);
    Route::get($base . '/certificates',         [LinkedActController::class, 'certificates']);
    Route::post($base . '/signer-confirmation', [LinkedActController::class, 'signerConfirmation']);
    Route::post($base . '/export',              [LinkedActController::class, 'export']);

    Route::get('audit-logs/kpi-summary',  [AuditLogController::class, 'kpiSummary']);
    Route::get('audit-logs',              [AuditLogController::class, 'index']);
    Route::get('audit-logs/{log_id}',     [AuditLogController::class, 'show']);
    Route::post('audit-logs/export',      [AuditLogController::class, 'export']);
});

Route::middleware('auth:api')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

// Dashboard & Journal APIs (v1) - Moved outside auth for testing
Route::prefix('v1')->group(function () {
    Route::get('/test-db', function () {
        try {
            \Illuminate\Support\Facades\DB::connection()->getPdo();
            return response()->json([
                'status' => 'connected',
                'database' => \Illuminate\Support\Facades\DB::getDatabaseName(),
                'server_info' => \Illuminate\Support\Facades\DB::select("SELECT @@VERSION as version")[0]->version
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    });

    // ── Dashboard ───────────────────────────────────────────────
    Route::get('dashboard/kpi-summary',                     [DashboardController::class, 'kpiSummary']);
    Route::get('dashboard/compliance-logs',                  [DashboardController::class, 'complianceLogs']);
    Route::get('audit-logs/{id}',                            [DashboardController::class, 'auditLogDetail']);
    Route::post('notaries/reminders/missing-signatures',     [DashboardController::class, 'sendMissingSignatureReminders']);

    // ── Journals ────────────────────────────────────────────────
    Route::get('journals',                                   [JournalController::class, 'index']);
    Route::get('journals/{id}',                              [JournalController::class, 'show']);
    Route::patch('journals/{id}/waive-thumbprint',           [JournalController::class, 'waiveThumbprint']);
});
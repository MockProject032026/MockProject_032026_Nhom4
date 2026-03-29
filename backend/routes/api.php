<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NotaryJournal\LinkedActController;
use App\Http\Controllers\NotaryJournal\AuditLogController;


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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
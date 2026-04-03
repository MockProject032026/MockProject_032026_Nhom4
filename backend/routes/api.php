<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SignerVerificationController;
use App\Http\Controllers\Api\BiometricController;
use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\EntryDetailController;


Route::prefix('v1')->group(function () {

    // SC_004:
    Route::prefix('journal-entries/{id}')->group(function () {
        Route::get('/', [EntryDetailController::class, 'show']);
    });
    // SC_005
    Route::prefix('journal-entries/{id}')->group(function () {
        Route::get('signer-verification', [SignerVerificationController::class, 'show']);
        Route::put('signer-verification', [SignerVerificationController::class, 'update']);
        Route::get('signer-verification/compliance', [SignerVerificationController::class, 'compliance']);
        Route::post('finalize', [SignerVerificationController::class, 'finalize']);

        Route::get('attachments', [AttachmentController::class, 'index']);
        Route::post('attachments', [AttachmentController::class, 'store']);
        Route::delete('attachments/{attachmentId}', [AttachmentController::class, 'destroy']);

        // SC_006
        Route::get('biometrics', [BiometricController::class, 'show']);
        Route::post('biometrics', [BiometricController::class, 'store']);
        Route::get('biometrics/metadata', [BiometricController::class, 'metadata']);
        Route::delete('biometrics/{biometricId}', [BiometricController::class, 'destroy']);
    });

    Route::get('notaries/{notaryId}/biometrics', [BiometricController::class, 'notaryBiometrics']);
    Route::post('notaries/{notaryId}/biometrics',   [BiometricController::class, 'storeNotaryBiometrics']);
    Route::delete('notaries/{notaryId}/biometrics', [BiometricController::class, 'destroyNotaryBiometrics']);
});

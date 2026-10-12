<?php

use App\Http\Controllers\Api\InternalController;
use App\Http\Controllers\Api\LicenseController;
use App\Http\Controllers\Api\UpdateController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:license-api')->group(function () {
    // Called by customers' installations (external or internal API key).
    Route::middleware('api.key')->group(function () {
        Route::post('check_connection', [LicenseController::class, 'checkConnection']);
        Route::post('activate_license', [LicenseController::class, 'activate']);
        Route::post('verify_license', [LicenseController::class, 'verify']);
        Route::post('deactivate_license', [LicenseController::class, 'deactivate']);
        Route::post('latest_version', [UpdateController::class, 'latestVersion']);
        Route::post('check_update', [UpdateController::class, 'checkUpdate']);
        Route::post('download_update', [UpdateController::class, 'downloadUpdate']);
    });

    // Trusted integrations only.
    Route::middleware('api.key:internal')->group(function () {
        Route::post('create_license', [InternalController::class, 'createLicense']);
        Route::post('get_license', [InternalController::class, 'getLicense']);
        Route::post('block_license', [InternalController::class, 'blockLicense']);
        Route::post('unblock_license', [InternalController::class, 'unblockLicense']);
    });

    // Signed, expiring link handed out by download_update.
    Route::get('updates/{release}/{type}/{activation}', [UpdateController::class, 'file'])
        ->whereIn('type', ['main', 'sql'])
        ->middleware('signed')
        ->name('api.updates.file');
});

<?php

use App\Http\Controllers\Api\Admin\StudentController;
use App\Http\Controllers\Api\Admin\StudentImportController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API PKKMB UNINUS 2026
|--------------------------------------------------------------------------
|
| Kontrak lengkap: docs/03-api-spec.md
| Seluruh response mengikuti { success, data|message }.
|
*/

Route::prefix('v1')->group(function () {

    /*
     * Autentikasi panitia.
     * throttle:5,1 = maks 5 percobaan per menit per IP. Penguncian PER AKUN
     * (8 kegagalan berturut-turut) ditangani di dalam controller — keduanya
     * diperlukan, lihat docs/04-security.md §1.
     */
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
    });

    /*
     * Dashboard admin. `role:admin` berarti operator (petugas scan) mendapat 403,
     * bukan sekadar tidak melihat menunya (docs/04-security.md §1).
     */
    Route::middleware(['auth:sanctum', 'role:admin', 'throttle:120,1'])
        ->prefix('admin')
        ->group(function () {

            Route::post('students/import', [StudentImportController::class, 'store']);
            Route::get('import-batches', [StudentImportController::class, 'index']);
            Route::get('import-batches/{importBatch}', [StudentImportController::class, 'show']);

            Route::post('students/{student}/rotate-token', [StudentController::class, 'rotateToken']);
            Route::apiResource('students', StudentController::class);
        });
});

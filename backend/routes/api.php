<?php

use App\Http\Controllers\Api\Admin\AttendanceController;
use App\Http\Controllers\Api\Admin\AttendanceSessionController;
use App\Http\Controllers\Api\Admin\StudentController;
use App\Http\Controllers\Api\Admin\StudentImportController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DownloadController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\ScanController;
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
     * ── Publik: mahasiswa baru, TANPA login ──────────────────────────────
     *
     * POST (bukan GET) karena NIM adalah data pribadi: ?nim=... akan tercatat
     * di log akses Nginx, riwayat browser, dan header Referer
     * (docs/04-security.md §2 butir 3).
     */
    Route::post('lookup', LookupController::class)->middleware('throttle:lookup');

    /*
     * Unduhan bertanda tangan, kedaluwarsa 15 menit. Middleware `signed` yang
     * menjaga — tanpa tanda tangan yang sah, permintaan ditolak sebelum
     * menyentuh controller.
     */
    Route::middleware(['signed', 'throttle:20,1'])->group(function () {
        Route::get('download/nametag/{student}', [DownloadController::class, 'nametag'])
            ->name('unduh.nametag');

        Route::get('download/qr/{student}', [DownloadController::class, 'qr'])
            ->name('unduh.qr');
    });

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

            // Sesi presensi
            Route::post('attendance-sessions/{attendanceSession}/activate', [AttendanceSessionController::class, 'activate']);
            Route::apiResource('attendance-sessions', AttendanceSessionController::class)
                ->except(['show']);

            // Rekap kehadiran
            Route::get('attendances/export', [AttendanceController::class, 'export']);
            Route::get('attendances', [AttendanceController::class, 'index']);
            Route::post('attendances', [AttendanceController::class, 'store']);
            Route::delete('attendances/{attendance}', [AttendanceController::class, 'destroy']);
            Route::get('dashboard/stats', [AttendanceController::class, 'stats']);
        });

    /*
     * ── Presensi: admin DAN operator ─────────────────────────────────────
     *
     * Batas laju longgar (180/menit) karena 4 titik memindai bersamaan dalam
     * jendela 45 menit — batas yang terlalu ketat justru akan mengunci petugas
     * di tengah antrean (bahan/README.md §4).
     */
    Route::middleware(['auth:sanctum', 'role:admin,operator', 'throttle:180,1'])
        ->group(function () {
            Route::get('scan/context', [ScanController::class, 'context']);
            Route::get('scan/recent', [ScanController::class, 'recent']);
            Route::post('scan/manual', [ScanController::class, 'manual']);
            Route::post('scan', [ScanController::class, 'scan']);
        });
});

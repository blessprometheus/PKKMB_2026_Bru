<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Services\StudentImportService;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Impor data mahasiswa dari Excel/CSV. Lihat docs/03-api-spec.md §4.2.
 *
 * Keamanan berkas unggahan: docs/04-security.md §5.
 */
class StudentImportController extends Controller
{
    public function store(Request $request, StudentImportService $service): JsonResponse
    {
        $maxKb = (int) config('pkkmb.import.max_file_size_kb');

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:'.$maxKb],
        ], [
            'file.mimes' => 'Berkas harus bertipe .xlsx, .xls, atau .csv.',
            'file.max' => 'Ukuran berkas maksimal '.round($maxKb / 1024).' MB.',
        ]);

        $file = $request->file('file');

        // Validasi MIME ASLI, bukan sekadar ekstensi atau Content-Type dari klien —
        // keduanya sepenuhnya dikendalikan pengunggah (docs/04-security.md §5.1).
        $allowedMimes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'application/zip',          // .xlsx sebenarnya adalah arsip zip
            'text/plain',               // .csv
            'text/csv',
            'application/csv',
        ];

        if (! in_array($file->getMimeType(), $allowedMimes, true)) {
            return ApiResponse::error(
                'Isi berkas bukan Excel atau CSV yang sah, meskipun namanya berakhiran itu.',
                422,
            );
        }

        // Disimpan di storage/app/private/imports — DI LUAR webroot, dengan nama
        // acak. Nama asli hanya dicatat di database.
        $storedPath = $file->store('imports', 'local');

        $batch = ImportBatch::create([
            'admin_id' => $request->user()->id,
            'original_filename' => mb_substr($file->getClientOriginalName(), 0, 255),
            'stored_path' => $storedPath,
            'status' => ImportBatch::STATUS_PROCESSING,
        ]);

        try {
            $absolutePath = Storage::disk('local')->path($storedPath);

            $batch = DB::transaction(fn () => $service->import($absolutePath, $batch));
        } catch (RuntimeException $e) {
            // Kegagalan yang bisa dijelaskan ke admin (mis. header tidak cocok).
            $batch->update(['status' => ImportBatch::STATUS_FAILED]);

            return ApiResponse::error($e->getMessage(), 422);
        } catch (Throwable $e) {
            $batch->update(['status' => ImportBatch::STATUS_FAILED]);

            report($e);

            return ApiResponse::error(
                'Berkas gagal diproses. Periksa apakah formatnya benar, lalu coba lagi.',
                422,
            );
        }

        AuditLogger::record('import.completed', 'import_batch', $batch->id, [
            'inserted' => $batch->inserted_count,
            'updated' => $batch->updated_count,
            'failed' => $batch->failed_count,
        ]);

        return ApiResponse::ok($this->toArray($batch));
    }

    public function index(): JsonResponse
    {
        $batches = ImportBatch::with('admin:id,name')
            ->latest('id')
            ->paginate(20);

        return ApiResponse::paginated($batches, fn (ImportBatch $b) => $this->toArray($b, withErrors: false));
    }

    public function show(ImportBatch $importBatch): JsonResponse
    {
        return ApiResponse::ok($this->toArray($importBatch));
    }

    /** @return array<string, mixed> */
    private function toArray(ImportBatch $batch, bool $withErrors = true): array
    {
        $data = [
            'batch_id' => $batch->id,
            'original_filename' => $batch->original_filename,
            'status' => $batch->status,
            'total_rows' => $batch->total_rows,
            'inserted_count' => $batch->inserted_count,
            'updated_count' => $batch->updated_count,
            'failed_count' => $batch->failed_count,
            'imported_by' => $batch->admin?->name,
            'created_at' => $batch->created_at?->toIso8601String(),
        ];

        if ($withErrors) {
            $data['errors'] = $batch->error_report ?? [];
        }

        return $data;
    }
}

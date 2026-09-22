<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\ScanLog;
use App\Models\Student;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Inti presensi hari-H. Lihat docs/03-api-spec.md §5.
 *
 * Dipakai petugas di pintu masuk sambil menghadapi antrean. Dokumen panitia
 * memberi jendela **45 menit** untuk ±550 mahasiswa di 4 titik
 * (bahan/README.md §4) — setiap detik latensi dikali ratusan orang.
 *
 * Dua keputusan yang menentukan benar-tidaknya modul ini:
 *
 * 1. **Selalu 200.** Hasil ada di `data.result`, bukan di kode status. Halaman
 *    petugas harus menampilkan ketiga keadaan sama cepatnya; 404/409 membuat
 *    penanganan di frontend berbelit justru saat antrean sedang panjang.
 * 2. **Duplikat dicegah database, bukan PHP.** Empat scanner memindai bersamaan;
 *    pola "cek dulu lalu insert" bisa kalah balapan dan menghasilkan dua baris.
 */
class ScanController extends Controller
{
    public function context(Request $request): JsonResponse
    {
        $sessions = AttendanceSession::orderBy('starts_at')->get()->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'starts_at' => $s->starts_at?->toIso8601String(),
            'ends_at' => $s->ends_at?->toIso8601String(),
            'is_active' => $s->is_active,
            'is_open_now' => $s->isOpenAt(now()),
        ]);

        return ApiResponse::ok([
            'operator' => ['name' => $request->user()->name, 'role' => $request->user()->role],
            'sessions' => $sessions,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /** Pemindaian QR oleh scanner gun. */
    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'size:32', 'regex:/^[0-9a-f]{32}$/'],
            'attendance_session_id' => ['required', 'integer', 'exists:attendance_sessions,id'],
            'device_label' => ['nullable', 'string', 'max:60'],
        ], [
            'token.size' => 'Kode QR tidak dikenali.',
            'token.regex' => 'Kode QR tidak dikenali.',
        ]);

        $student = Student::where('attendance_token', $data['token'])->first();

        return $this->catat(
            $request,
            $student,
            (int) $data['attendance_session_id'],
            $data['device_label'] ?? null,
            Attendance::METHOD_QR,
            $data['token'],
        );
    }

    /**
     * Cadangan: petugas mengetik NIM.
     *
     * Wajib ada — akan selalu ada mahasiswa yang nametag-nya rusak, tertinggal,
     * atau tidak pernah dicetak (M10 di docs/01-prd.md §5).
     */
    public function manual(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nim' => ['required', 'string', 'max:30', 'regex:/^[0-9A-Za-z.\-]+$/'],
            'attendance_session_id' => ['required', 'integer', 'exists:attendance_sessions,id'],
            'device_label' => ['nullable', 'string', 'max:60'],
        ]);

        $student = Student::where('nim', trim($data['nim']))->first();

        return $this->catat(
            $request,
            $student,
            (int) $data['attendance_session_id'],
            $data['device_label'] ?? null,
            Attendance::METHOD_MANUAL,
            $data['nim'],
        );
    }

    /** Sepuluh pemindaian terakhir — bukti visual bagi petugas bahwa sistem masih hidup. */
    public function recent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['required', 'integer', 'exists:attendance_sessions,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $recent = Attendance::with('student:id,nim,name')
            ->where('attendance_session_id', $data['session_id'])
            // Urutan kedua berdasarkan id wajib ada: di jam sibuk beberapa
            // pemindaian jatuh pada detik yang sama, dan tanpa pemecah seri
            // urutannya berubah-ubah — petugas akan mengira daftarnya kacau.
            ->orderByDesc('scanned_at')
            ->orderByDesc('id')
            ->limit($data['limit'] ?? 10)
            ->get()
            ->map(fn (Attendance $a) => [
                'name' => $a->student?->name,
                'nim' => $a->student?->nim,
                'scanned_at' => $a->scanned_at?->toIso8601String(),
                'method' => $a->method,
            ]);

        return ApiResponse::ok($recent);
    }

    /**
     * Jalur pencatatan bersama untuk QR maupun NIM manual.
     */
    private function catat(
        Request $request,
        ?Student $student,
        int $sessionId,
        ?string $deviceLabel,
        string $method,
        string $inputMentah,
    ): JsonResponse {
        $session = AttendanceSession::find($sessionId);
        $now = now();

        $tulisLog = fn (string $result) => ScanLog::create([
            'admin_id' => $request->user()->id,
            'attendance_session_id' => $sessionId,
            'student_id' => $student?->id,
            'result' => $result,
            // HASH dari input mentah. Kalau token disimpan polos, tabel log ini
            // menjadi daftar QR yang bisa dipakai ulang (docs/04-security.md §9).
            'raw_input_hash' => hash('sha256', $inputMentah),
            'ip' => $request->ip(),
        ]);

        if ($student === null) {
            $tulisLog(ScanLog::RESULT_UNKNOWN_TOKEN);

            return ApiResponse::ok([
                'result' => ScanLog::RESULT_UNKNOWN_TOKEN,
                'message' => $method === Attendance::METHOD_MANUAL
                    ? 'NIM TIDAK DITEMUKAN'
                    : 'QR TIDAK DIKENAL',
                'student' => null,
                'scanned_at' => null,
            ]);
        }

        // Scan di luar jendela waktu ditolak dengan pesan jelas, bukan diterima
        // diam-diam — supaya tidak ada kehadiran tercatat di sesi yang salah.
        if ($session === null || ! $session->isOpenAt($now)) {
            $tulisLog(ScanLog::RESULT_SESSION_CLOSED);

            $belumMulai = $session !== null && $now < $session->starts_at;

            return ApiResponse::ok([
                'result' => ScanLog::RESULT_SESSION_CLOSED,
                'message' => $belumMulai ? 'SESI BELUM DIBUKA' : 'SESI SUDAH DITUTUP',
                'student' => $student->toScanArray(),
                'scanned_at' => null,
            ]);
        }

        /*
         * INSERT ... ON CONFLICT DO NOTHING lewat insertOrIgnore().
         *
         * Batasan UNIQUE (student_id, attendance_session_id) yang menolak baris
         * kedua — bukan pengecekan di PHP. Jumlah baris yang benar-benar masuk
         * yang membedakan "tercatat" dari "sudah absen".
         */
        $masuk = DB::table('attendances')->insertOrIgnore([
            'student_id' => $student->id,
            'attendance_session_id' => $sessionId,
            'admin_id' => $request->user()->id,
            'scanned_at' => $now,
            'method' => $method,
            'device_label' => $deviceLabel,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($masuk === 0) {
            $sebelumnya = Attendance::where('student_id', $student->id)
                ->where('attendance_session_id', $sessionId)
                ->value('scanned_at');

            $tulisLog(ScanLog::RESULT_DUPLICATE);

            return ApiResponse::ok([
                'result' => ScanLog::RESULT_DUPLICATE,
                'message' => 'SUDAH ABSEN '.($sebelumnya?->format('H:i') ?? ''),
                'student' => $student->toScanArray(),
                'scanned_at' => $sebelumnya?->toIso8601String(),
            ]);
        }

        $tulisLog(ScanLog::RESULT_RECORDED);

        return ApiResponse::ok([
            'result' => ScanLog::RESULT_RECORDED,
            'message' => 'TERCATAT',
            'student' => $student->toScanArray(),
            'scanned_at' => $now->toIso8601String(),
        ]);
    }
}

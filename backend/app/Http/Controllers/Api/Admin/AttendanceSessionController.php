<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Pengelolaan sesi presensi. Lihat docs/03-api-spec.md §4.3.
 *
 * Contoh nyata dari dokumen panitia (bahan/README.md §4):
 * "Registrasi daftar hadir" hari pertama berlangsung 06:30–07:15 WIB,
 * Selasa 29 September 2026.
 */
class AttendanceSessionController extends Controller
{
    public function index(): JsonResponse
    {
        $sessions = AttendanceSession::withCount('attendances')
            ->orderBy('starts_at')
            ->get()
            ->map(fn (AttendanceSession $s) => $this->toArray($s));

        return ApiResponse::ok($sessions);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validasi($request);

        $session = AttendanceSession::create($data);

        AuditLogger::record('session.created', 'attendance_session', $session->id);

        return ApiResponse::created($this->toArray($session));
    }

    public function update(Request $request, AttendanceSession $attendanceSession): JsonResponse
    {
        $attendanceSession->update($this->validasi($request));

        AuditLogger::record('session.updated', 'attendance_session', $attendanceSession->id);

        return ApiResponse::ok($this->toArray($attendanceSession));
    }

    /**
     * Mengaktifkan satu sesi dan menonaktifkan sisanya.
     *
     * Hanya satu sesi boleh aktif: petugas memilih sesi dari daftar, dan kalau
     * ada dua yang aktif, kehadiran bisa tercatat di sesi yang salah — kesalahan
     * yang baru ketahuan saat rekap dicetak setelah acara bubar.
     */
    public function activate(AttendanceSession $attendanceSession): JsonResponse
    {
        AttendanceSession::where('id', '!=', $attendanceSession->id)->update(['is_active' => false]);

        $attendanceSession->update(['is_active' => true]);

        AuditLogger::record('session.activated', 'attendance_session', $attendanceSession->id);

        return ApiResponse::ok($this->toArray($attendanceSession->refresh()));
    }

    public function destroy(AttendanceSession $attendanceSession): JsonResponse
    {
        // Menghapus sesi berarti menghapus seluruh kehadiran di dalamnya
        // (ON DELETE CASCADE). Kehadiran adalah dokumen resmi — jangan sampai
        // hilang karena satu klik yang tidak disengaja.
        if ($attendanceSession->attendances()->exists()) {
            return ApiResponse::error(
                'Sesi ini tidak bisa dihapus karena sudah memuat data kehadiran. '.
                'Nonaktifkan saja bila tidak dipakai.',
                422,
            );
        }

        $id = $attendanceSession->id;
        $attendanceSession->delete();

        AuditLogger::record('session.deleted', 'attendance_session', $id);

        return ApiResponse::ok(['message' => 'Sesi presensi dihapus.']);
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'event_date' => ['required', 'date'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'ends_at.after' => 'Waktu selesai harus setelah waktu mulai.',
        ]);
    }

    /** @return array<string, mixed> */
    private function toArray(AttendanceSession $session): array
    {
        return [
            'id' => $session->id,
            'name' => $session->name,
            'event_date' => $session->event_date?->toDateString(),
            'starts_at' => $session->starts_at?->toIso8601String(),
            'ends_at' => $session->ends_at?->toIso8601String(),
            'is_active' => $session->is_active,
            'is_open_now' => $session->isOpenAt(now()),
            'attendances_count' => $session->attendances_count ?? $session->attendances()->count(),
        ];
    }
}

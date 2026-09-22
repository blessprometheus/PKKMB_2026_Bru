<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Student;
use App\Services\ExcelExportService;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rekap kehadiran dan statistik dasbor. Lihat docs/03-api-spec.md §4.4.
 */
class AttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['required', 'integer', 'exists:attendance_sessions,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $attendances = Attendance::with('student:id,nim,name,faculty,study_program,group_name')
            ->where('attendance_session_id', $data['session_id'])
            ->latest('scanned_at')
            ->paginate($data['per_page'] ?? 25);

        return ApiResponse::paginated($attendances, fn (Attendance $a) => [
            'id' => $a->id,
            'nim' => $a->student?->nim,
            'name' => $a->student?->name,
            'faculty' => $a->student?->faculty,
            'study_program' => $a->student?->study_program,
            'group_name' => $a->student?->group_name,
            'scanned_at' => $a->scanned_at?->toIso8601String(),
            'method' => $a->method,
            'device_label' => $a->device_label,
        ]);
    }

    /**
     * Ekspor rekap ke Excel.
     *
     * Menyertakan yang **BELUM hadir** juga — itu justru yang paling dicari
     * panitia setelah acara. Rekap yang hanya memuat daftar hadir memaksa
     * mereka mencocokkan manual dengan daftar peserta.
     */
    public function export(Request $request, ExcelExportService $excel): Response
    {
        $data = $request->validate([
            'session_id' => ['required', 'integer', 'exists:attendance_sessions,id'],
        ]);

        $session = AttendanceSession::findOrFail($data['session_id']);

        $students = Student::with(['attendances' => fn ($q) => $q->where('attendance_session_id', $session->id)])
            ->orderBy('faculty')
            ->orderBy('nim')
            ->get();

        $baris = $students->map(function (Student $s) {
            $hadir = $s->attendances->first();

            return [
                $s->nim,
                $s->name,
                $s->faculty,
                $s->study_program,
                $s->group_name ?? '',
                $hadir ? 'HADIR' : 'BELUM HADIR',
                $hadir?->scanned_at?->format('Y-m-d H:i:s') ?? '',
                $hadir?->method === Attendance::METHOD_MANUAL ? 'Input manual' : ($hadir ? 'QR' : ''),
                $hadir?->device_label ?? '',
            ];
        });

        $isi = $excel->buat(
            ['NIM', 'Nama', 'Fakultas', 'Program Studi', 'Kelompok', 'Status', 'Waktu Hadir', 'Cara', 'Titik'],
            $baris,
            'Rekap '.$session->name,
        );

        AuditLogger::record('attendance.exported', 'attendance_session', $session->id, [
            'total_rows' => $baris->count(),
        ]);

        $namaBerkas = 'rekap-kehadiran-'.$session->event_date?->format('Y-m-d').'-'.$session->id.'.xlsx';

        return response($isi, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$namaBerkas.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    /** Pencatatan manual oleh admin, mis. memasukkan hasil daftar hadir kertas. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'attendance_session_id' => ['required', 'integer', 'exists:attendance_sessions,id'],
        ]);

        $masuk = DB::table('attendances')->insertOrIgnore([
            'student_id' => $data['student_id'],
            'attendance_session_id' => $data['attendance_session_id'],
            'admin_id' => $request->user()->id,
            'scanned_at' => now(),
            'method' => Attendance::METHOD_MANUAL,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($masuk === 0) {
            return ApiResponse::error('Mahasiswa ini sudah tercatat hadir pada sesi tersebut.', 422);
        }

        AuditLogger::record('attendance.created_manual', 'student', (int) $data['student_id'], [
            'attendance_session_id' => $data['attendance_session_id'],
        ]);

        return ApiResponse::created(['message' => 'Kehadiran dicatat.']);
    }

    /** Koreksi salah scan. Wajib tercatat — ini menghapus dokumen resmi. */
    public function destroy(Attendance $attendance): JsonResponse
    {
        $id = $attendance->id;
        $studentId = $attendance->student_id;
        $sessionId = $attendance->attendance_session_id;

        $attendance->delete();

        AuditLogger::record('attendance.deleted', 'attendance', $id, [
            'student_id' => $studentId,
            'attendance_session_id' => $sessionId,
        ]);

        return ApiResponse::ok(['message' => 'Catatan kehadiran dihapus.']);
    }

    /** Ringkasan untuk dasbor. */
    public function stats(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['nullable', 'integer', 'exists:attendance_sessions,id'],
        ]);

        $totalStudents = Student::count();

        $session = isset($data['session_id'])
            ? AttendanceSession::find($data['session_id'])
            : AttendanceSession::where('is_active', true)->first();

        if ($session === null) {
            return ApiResponse::ok([
                'total_students' => $totalStudents,
                'active_session' => null,
                'present_count' => 0,
                'absent_count' => $totalStudents,
                'by_faculty' => [],
                'scans_per_10min' => [],
            ]);
        }

        $present = Attendance::where('attendance_session_id', $session->id)->count();

        $byFaculty = Student::query()
            ->select('faculty')
            ->selectRaw('count(*) as total')
            ->selectRaw(
                'count(a.id) as present',
            )
            ->leftJoin('attendances as a', function ($join) use ($session) {
                $join->on('a.student_id', '=', 'students.id')
                    ->where('a.attendance_session_id', '=', $session->id);
            })
            ->groupBy('faculty')
            ->orderBy('faculty')
            ->get()
            ->map(fn ($r) => [
                'faculty' => $r->faculty,
                'total' => (int) $r->total,
                'present' => (int) $r->present,
            ]);

        // Laju kedatangan per 10 menit. Dengan jendela registrasi hanya 45 menit
        // (bahan/README.md §4), panitia perlu melihat apakah antrean menumpuk.
        $perSepuluhMenit = Attendance::query()
            ->where('attendance_session_id', $session->id)
            ->selectRaw("to_char(date_trunc('hour', scanned_at) + floor(extract(minute from scanned_at)/10) * interval '10 minute', 'HH24:MI') as jam")
            ->selectRaw('count(*) as jumlah')
            ->groupBy('jam')
            ->orderBy('jam')
            ->get()
            ->map(fn ($r) => ['at' => $r->jam, 'count' => (int) $r->jumlah]);

        return ApiResponse::ok([
            'total_students' => $totalStudents,
            'active_session' => ['id' => $session->id, 'name' => $session->name],
            'present_count' => $present,
            'absent_count' => max(0, $totalStudents - $present),
            'by_faculty' => $byFaculty,
            'scans_per_10min' => $perSepuluhMenit,
        ]);
    }
}

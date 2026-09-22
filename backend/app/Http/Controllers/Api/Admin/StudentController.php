<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StudentRequest;
use App\Models\Student;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD mahasiswa untuk dashboard admin. Lihat docs/03-api-spec.md §4.1.
 *
 * Seluruh rute di sini dibatasi `role:admin` — operator (petugas scan) TIDAK
 * boleh membuka daftar lengkap mahasiswa (docs/04-security.md §1).
 */
class StudentController extends Controller
{
    /** Batas atas paginasi, supaya tidak ada yang meminta seluruh tabel sekaligus. */
    private const MAX_PER_PAGE = 100;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'faculty' => ['nullable', 'string', 'max:120'],
            'group_name' => ['nullable', 'string', 'max:80'],
            'session_id' => ['nullable', 'integer', 'exists:attendance_sessions,id'],
            'attendance_status' => ['nullable', 'in:hadir,belum'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ]);

        $query = Student::query()
            ->search($validated['q'] ?? null)
            ->when($validated['faculty'] ?? null, fn ($q, $f) => $q->where('faculty', $f))
            ->when($validated['group_name'] ?? null, fn ($q, $g) => $q->where('group_name', $g));

        // Filter kehadiran hanya bermakna bila sesinya disebut — "sudah absen"
        // tanpa menyebut sesi yang mana tidak punya arti.
        $sessionId = $validated['session_id'] ?? null;
        $status = $validated['attendance_status'] ?? null;

        if ($status !== null && $sessionId === null) {
            return ApiResponse::error(
                'Filter status kehadiran membutuhkan sesi presensi yang dipilih.',
                422,
            );
        }

        if ($sessionId !== null && $status !== null) {
            $relation = fn ($q) => $q->where('attendance_session_id', $sessionId);

            $query = $status === 'hadir'
                ? $query->whereHas('attendances', $relation)
                : $query->whereDoesntHave('attendances', $relation);
        }

        // Tanpa eager load ini, menampilkan 100 baris berarti 100 query tambahan
        // hanya untuk mengetahui siapa yang sudah hadir.
        if ($sessionId !== null) {
            $query->with([
                'attendances' => fn ($q) => $q->where('attendance_session_id', $sessionId),
            ]);
        }

        $students = $query->orderBy('nim')
            ->paginate($validated['per_page'] ?? 25)
            ->withQueryString();

        return ApiResponse::paginated($students, fn (Student $s) => $this->toAdminArray($s, $sessionId));
    }

    public function store(StudentRequest $request): JsonResponse
    {
        $student = Student::create($request->validated());

        AuditLogger::record('student.created', 'student', $student->id);

        return ApiResponse::created($this->toAdminArray($student));
    }

    public function show(Student $student): JsonResponse
    {
        return ApiResponse::ok($this->toAdminArray($student));
    }

    public function update(StudentRequest $request, Student $student): JsonResponse
    {
        $student->update($request->validated());

        // Hanya mencatat NAMA KOLOM yang berubah, bukan nilainya — nilai lama
        // dan baru adalah data pribadi (docs/04-security.md §9).
        AuditLogger::record('student.updated', 'student', $student->id, [
            'changed_fields' => array_keys($student->getChanges()),
        ]);

        return ApiResponse::ok($this->toAdminArray($student));
    }

    public function destroy(Student $student): JsonResponse
    {
        $id = $student->id;
        $student->delete();

        AuditLogger::record('student.deleted', 'student', $id);

        return ApiResponse::ok(['message' => 'Data mahasiswa dihapus.']);
    }

    /**
     * Mencabut QR lama dan menerbitkan token baru.
     *
     * Dipakai kalau ada indikasi QR seseorang disalahgunakan — QR yang sudah
     * tercetak langsung tidak berlaku (docs/02-data-model.md §4).
     */
    public function rotateToken(Student $student): JsonResponse
    {
        $student->forceFill(['attendance_token' => Student::generateToken()])->save();

        AuditLogger::record('student.token_rotated', 'student', $student->id);

        return ApiResponse::ok([
            'message' => 'Token QR diperbarui. QR lama mahasiswa ini sudah tidak berlaku.',
        ]);
    }

    /**
     * Bentuk data untuk dashboard admin.
     *
     * `attendance_token` TIDAK pernah ikut — tidak di sini, tidak di mana pun
     * sebagai teks (docs/03-api-spec.md §6).
     *
     * @return array<string, mixed>
     */
    private function toAdminArray(Student $student, ?int $sessionId = null): array
    {
        $data = [
            'id' => $student->id,
            'nim' => $student->nim,
            'name' => $student->name,
            'faculty' => $student->faculty,
            'study_program' => $student->study_program,
            'group_name' => $student->group_name,
            'gender' => $student->gender,
            'birth_date' => $student->birth_date?->toDateString(),
            'phone' => $student->phone,
            'email' => $student->email,
        ];

        if ($sessionId !== null) {
            $data['is_present'] = $student->attendances
                ->where('attendance_session_id', $sessionId)
                ->isNotEmpty();
        }

        return $data;
    }
}

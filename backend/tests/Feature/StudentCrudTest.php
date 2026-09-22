<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRUD mahasiswa untuk dashboard admin. Lihat docs/03-api-spec.md §4.1.
 */
class StudentCrudTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->admin()->create();
    }

    /** @return array<string, string> */
    private function dataValid(array $ubah = []): array
    {
        return array_merge([
            'nim' => '20260012345',
            'name' => 'Ahmad Fauzi',
            'faculty' => 'Fakultas Teknik',
            'study_program' => 'Teknik Informatika',
            'group_name' => 'Gugus 3',
            'gender' => 'L',
        ], $ubah);
    }

    public function test_admin_bisa_menambah_mahasiswa_dan_token_langsung_terbentuk(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/students', $this->dataValid());

        $response->assertStatus(201)->assertJsonPath('data.nim', '20260012345');

        $student = Student::where('nim', '20260012345')->first();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $student->attendance_token);
    }

    /**
     * `attendance_token` tidak boleh muncul sebagai teks di response mana pun —
     * termasuk response admin (docs/03-api-spec.md §6).
     */
    public function test_token_qr_tidak_pernah_ikut_di_response_admin(): void
    {
        $student = Student::factory()->create();

        $daftar = $this->actingAs($this->admin)->getJson('/api/v1/admin/students');
        $detail = $this->actingAs($this->admin)->getJson("/api/v1/admin/students/{$student->id}");

        $this->assertStringNotContainsString($student->attendance_token, $daftar->getContent());
        $this->assertStringNotContainsString($student->attendance_token, $detail->getContent());
        $this->assertStringNotContainsString('attendance_token', $detail->getContent());
    }

    public function test_nim_ganda_ditolak_dengan_pesan_bahasa_indonesia(): void
    {
        Student::factory()->create(['nim' => '20260012345']);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/students', $this->dataValid());

        $response->assertStatus(422)
            ->assertJsonPath('errors.nim.0', 'NIM ini sudah terdaftar atas nama mahasiswa lain.');
    }

    public function test_pencarian_nama_tidak_peduli_huruf_besar_kecil(): void
    {
        Student::factory()->create(['name' => 'Siti Aminah', 'nim' => '20260011111']);
        Student::factory()->create(['name' => 'Budi Santoso', 'nim' => '20260022222']);

        // PostgreSQL: LIKE sensitif huruf besar/kecil, ILIKE tidak.
        $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/students?q=siti');

        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Siti Aminah');
    }

    public function test_pencarian_juga_menemukan_berdasarkan_nim(): void
    {
        Student::factory()->create(['nim' => '20260098765']);
        Student::factory()->count(2)->create();

        $this->actingAs($this->admin)->getJson('/api/v1/admin/students?q=98765')
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_paginasi_dibatasi_maksimal_seratus_per_halaman(): void
    {
        $this->actingAs($this->admin)->getJson('/api/v1/admin/students?per_page=5000')
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['per_page']]);
    }

    /**
     * "Sudah absen" tanpa menyebut sesi yang mana tidak punya arti — jadi
     * filternya harus ditolak, bukan diam-diam mengembalikan hasil yang salah.
     */
    public function test_filter_kehadiran_tanpa_sesi_ditolak(): void
    {
        $this->actingAs($this->admin)->getJson('/api/v1/admin/students?attendance_status=hadir')
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_filter_kehadiran_memisahkan_yang_hadir_dan_belum(): void
    {
        $sesi = AttendanceSession::create([
            'name' => 'Hari 1 — Pembukaan',
            'event_date' => '2026-09-29',
            'starts_at' => '2026-09-29 07:00:00',
            'ends_at' => '2026-09-29 12:00:00',
            'is_active' => true,
        ]);

        $hadir = Student::factory()->create(['nim' => '20260011111']);
        Student::factory()->create(['nim' => '20260022222']);

        Attendance::create([
            'student_id' => $hadir->id,
            'attendance_session_id' => $sesi->id,
            'scanned_at' => now(),
            'method' => Attendance::METHOD_QR,
        ]);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/admin/students?session_id={$sesi->id}&attendance_status=hadir")
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nim', '20260011111')
            ->assertJsonPath('data.0.is_present', true);

        $this->actingAs($this->admin)
            ->getJson("/api/v1/admin/students?session_id={$sesi->id}&attendance_status=belum")
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nim', '20260022222');
    }

    public function test_perubahan_data_dicatat_di_jejak_audit_tanpa_menyimpan_nilainya(): void
    {
        $student = Student::factory()->create(['name' => 'Nama Lama']);

        $this->actingAs($this->admin)
            ->putJson("/api/v1/admin/students/{$student->id}", $this->dataValid([
                'nim' => $student->nim,
                'name' => 'Nama Baru',
            ]))
            ->assertOk();

        $this->assertSame('Nama Baru', $student->fresh()->name);

        $log = \App\Models\AuditLog::where('action', 'student.updated')->first();

        $this->assertNotNull($log);
        $this->assertContains('name', $log->meta['changed_fields']);
        // Nilai lama/baru adalah data pribadi — tidak boleh ikut tercatat.
        $this->assertStringNotContainsString('Nama Baru', json_encode($log->meta));
    }

    public function test_rotasi_token_membuat_qr_lama_tidak_berlaku(): void
    {
        $student = Student::factory()->create();
        $tokenLama = $student->attendance_token;

        $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/students/{$student->id}/rotate-token")
            ->assertOk();

        $tokenBaru = $student->fresh()->attendance_token;

        $this->assertNotSame($tokenLama, $tokenBaru);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $tokenBaru);
    }

    public function test_penghapusan_mahasiswa_tercatat_di_jejak_audit(): void
    {
        $student = Student::factory()->create();

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/admin/students/{$student->id}")
            ->assertOk();

        $this->assertDatabaseMissing('students', ['id' => $student->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'student.deleted',
            'target_id' => $student->id,
        ]);
    }

    public function test_nim_dengan_karakter_aneh_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/students', $this->dataValid(['nim' => "2026'; DROP TABLE students;--"]))
            ->assertStatus(422)
            ->assertJsonPath('errors.nim.0', 'NIM hanya boleh berisi huruf, angka, titik, dan strip.');

        // Tabelnya jelas masih ada — query memakai prepared statement.
        $this->assertSame(0, Student::count());
    }
}

<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\ScanLog;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Presensi hari-H. Lihat docs/03-api-spec.md §5.
 *
 * Modul ini yang menentukan acara berjalan atau tidak, jadi yang diuji bukan
 * hanya jalur bahagia melainkan justru jalur gagalnya.
 */
class ScanTest extends TestCase
{
    use RefreshDatabase;

    private Admin $operator;

    private AttendanceSession $sesi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operator = Admin::factory()->create(['role' => Admin::ROLE_OPERATOR]);

        // Meniru jendela nyata dari dokumen panitia: registrasi 06:30–07:15.
        $this->sesi = AttendanceSession::create([
            'name' => 'Registrasi Daftar Hadir — Hari 1',
            'event_date' => now()->toDateString(),
            'starts_at' => now()->subMinutes(10),
            'ends_at' => now()->addMinutes(35),
            'is_active' => true,
        ]);
    }

    private function pindai(Student $student, array $ubah = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->operator)->postJson('/api/v1/scan', array_merge([
            'token' => $student->attendance_token,
            'attendance_session_id' => $this->sesi->id,
            'device_label' => 'Pintu A',
        ], $ubah));
    }

    /**
     * Test regresi untuk bug yang ditemukan saat membangun Fase 4.
     *
     * `config/app.php` bawaan Laravel menulis `'timezone' => 'UTC'` secara
     * HARDCODED — `APP_TIMEZONE` di .env tidak terbaca sama sekali. Sementara
     * sesi PostgreSQL memakai timezone sistem server. Laravel mengirim string
     * tanpa offset, Postgres menafsirkannya di timezone-nya sendiri, dan
     * SELURUH stempel waktu bergeser 7 jam tanpa satu pun error muncul.
     *
     * Akibatnya di hari-H: sesi presensi menolak seluruh pemindaian karena
     * dianggap sudah lewat, dan jam kehadiran di rekap salah.
     */
    public function test_stempel_waktu_tidak_bergeser_antara_aplikasi_dan_database(): void
    {
        $sesi = AttendanceSession::create([
            'name' => 'Uji zona waktu',
            'event_date' => now()->toDateString(),
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
            'is_active' => true,
        ]);

        $dibaca = $sesi->fresh()->starts_at;

        $this->assertEqualsWithDelta(
            60,
            now()->diffInMinutes($dibaca, false),
            1,
            'Waktu bergeser saat ditulis ke dan dibaca dari PostgreSQL.',
        );

        $this->assertFalse(
            $sesi->fresh()->isOpenAt(now()),
            'Sesi yang baru mulai satu jam lagi dianggap sudah dibuka.',
        );

        // Stempel kehadiran juga harus bolak-balik tanpa bergeser.
        $student = Student::factory()->create();
        $this->pindai($student)->assertJsonPath('data.result', 'recorded');

        $tercatat = Attendance::where('student_id', $student->id)->value('scanned_at');

        $this->assertEqualsWithDelta(0, now()->diffInSeconds($tercatat, false), 5);
    }

    public function test_pemindaian_pertama_mencatat_kehadiran(): void
    {
        $student = Student::factory()->create(['name' => 'Ahmad Fauzi']);

        $this->pindai($student)
            ->assertOk()
            ->assertJsonPath('data.result', 'recorded')
            ->assertJsonPath('data.message', 'TERCATAT')
            ->assertJsonPath('data.student.name', 'Ahmad Fauzi');

        $this->assertDatabaseHas('attendances', [
            'student_id' => $student->id,
            'attendance_session_id' => $this->sesi->id,
            'method' => 'qr',
            'device_label' => 'Pintu A',
        ]);
    }

    /**
     * Inti pencegahan duplikat. Empat scanner memindai bersamaan; pola
     * "cek dulu lalu insert" bisa kalah balapan. Yang menolak baris kedua adalah
     * batasan UNIQUE di database (docs/02-data-model.md §2.4).
     */
    public function test_pemindaian_kedua_ditolak_dan_hanya_ada_satu_baris(): void
    {
        $student = Student::factory()->create();

        $this->pindai($student)->assertJsonPath('data.result', 'recorded');

        $this->pindai($student)
            ->assertOk()
            ->assertJsonPath('data.result', 'duplicate')
            ->assertJsonPath('data.student.nim', $student->nim);

        $this->assertSame(
            1,
            Attendance::where('student_id', $student->id)->count(),
            'Ada lebih dari satu baris kehadiran untuk mahasiswa yang sama di satu sesi.',
        );
    }

    /**
     * Sepuluh pemindaian beruntun untuk orang yang sama — meniru petugas yang
     * panik menembak berkali-kali karena layarnya terlambat berubah.
     */
    public function test_sepuluh_pemindaian_beruntun_tetap_menghasilkan_satu_baris(): void
    {
        $student = Student::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->pindai($student)->assertOk();
        }

        $this->assertSame(1, Attendance::where('student_id', $student->id)->count());
    }

    public function test_qr_tidak_dikenal_ditolak_dengan_pesan_jelas(): void
    {
        $this->actingAs($this->operator)
            ->postJson('/api/v1/scan', [
                'token' => str_repeat('a', 32),
                'attendance_session_id' => $this->sesi->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.result', 'unknown_token')
            ->assertJsonPath('data.message', 'QR TIDAK DIKENAL')
            ->assertJsonPath('data.student', null);

        $this->assertSame(0, Attendance::count());
    }

    public function test_sesi_yang_belum_dibuka_menolak_pemindaian(): void
    {
        $student = Student::factory()->create();

        $this->sesi->update([
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
        ]);

        $this->pindai($student)
            ->assertOk()
            ->assertJsonPath('data.result', 'session_closed')
            ->assertJsonPath('data.message', 'SESI BELUM DIBUKA');

        $this->assertSame(0, Attendance::count());
    }

    public function test_sesi_yang_sudah_ditutup_menolak_pemindaian(): void
    {
        $student = Student::factory()->create();

        $this->sesi->update([
            'starts_at' => now()->subHours(3),
            'ends_at' => now()->subHour(),
        ]);

        $this->pindai($student)
            ->assertJsonPath('data.result', 'session_closed')
            ->assertJsonPath('data.message', 'SESI SUDAH DITUTUP');

        $this->assertSame(0, Attendance::count());
    }

    public function test_sesi_nonaktif_menolak_pemindaian(): void
    {
        $student = Student::factory()->create();
        $this->sesi->update(['is_active' => false]);

        $this->pindai($student)->assertJsonPath('data.result', 'session_closed');
    }

    /** Cadangan wajib: nametag rusak, tertinggal, atau tidak pernah dicetak. */
    public function test_input_nim_manual_mencatat_kehadiran(): void
    {
        $student = Student::factory()->create(['nim' => '20260012345']);

        $this->actingAs($this->operator)
            ->postJson('/api/v1/scan/manual', [
                'nim' => '20260012345',
                'attendance_session_id' => $this->sesi->id,
                'device_label' => 'Pintu B',
            ])
            ->assertOk()
            ->assertJsonPath('data.result', 'recorded');

        $this->assertDatabaseHas('attendances', [
            'student_id' => $student->id,
            'method' => 'manual',
            'device_label' => 'Pintu B',
        ]);
    }

    public function test_nim_manual_yang_tidak_ada_ditolak(): void
    {
        $this->actingAs($this->operator)
            ->postJson('/api/v1/scan/manual', [
                'nim' => '20269999999',
                'attendance_session_id' => $this->sesi->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.message', 'NIM TIDAK DITEMUKAN');
    }

    /** Semua hasil, termasuk yang ditolak, harus tercatat untuk ditelusuri nanti. */
    public function test_setiap_hasil_pemindaian_tercatat_di_scan_logs(): void
    {
        $student = Student::factory()->create();

        $this->pindai($student);                                     // recorded
        $this->pindai($student);                                     // duplicate
        $this->actingAs($this->operator)->postJson('/api/v1/scan', [ // unknown_token
            'token' => str_repeat('b', 32),
            'attendance_session_id' => $this->sesi->id,
        ]);

        $this->assertSame(3, ScanLog::count());

        foreach (['recorded', 'duplicate', 'unknown_token'] as $hasil) {
            $this->assertTrue(
                ScanLog::where('result', $hasil)->exists(),
                "Hasil {$hasil} tidak tercatat di scan_logs.",
            );
        }
    }

    /**
     * Log tidak boleh berubah menjadi daftar QR yang bisa dipakai ulang.
     */
    public function test_scan_logs_menyimpan_hash_bukan_token_polos(): void
    {
        $student = Student::factory()->create();

        $this->pindai($student);

        $log = ScanLog::first();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $log->raw_input_hash);
        $this->assertNotSame($student->attendance_token, $log->raw_input_hash);
    }

    public function test_pemindaian_tanpa_login_ditolak(): void
    {
        $student = Student::factory()->create();

        $this->postJson('/api/v1/scan', [
            'token' => $student->attendance_token,
            'attendance_session_id' => $this->sesi->id,
        ])->assertStatus(401);

        $this->assertSame(0, Attendance::count());
    }

    /**
     * Test regresi — ditemukan lewat pengujian keamanan nyata di Fase 6.
     * `postJson()` selalu mengirim `Accept: application/json`; helper `post()`
     * polos di bawah TIDAK, meniru klien dunia nyata (curl polos, scanner gun
     * yang memakai skrip HTTP sederhana, alat uji pihak ketiga). Lihat catatan
     * lengkap di AuthTest::test_endpoint_admin_menolak_tamu_tanpa_header_accept.
     */
    public function test_pemindaian_tanpa_login_ditolak_meski_tanpa_header_accept(): void
    {
        $student = Student::factory()->create();

        $this->post('/api/v1/scan', [
            'token' => $student->attendance_token,
            'attendance_session_id' => $this->sesi->id,
        ])->assertStatus(401)->assertJson(['success' => false]);

        $this->assertSame(0, Attendance::count());
    }

    /** Operator boleh memindai, tapi tetap tidak boleh membuka data mahasiswa. */
    public function test_operator_boleh_memindai_tapi_tidak_boleh_melihat_rekap(): void
    {
        $student = Student::factory()->create();

        $this->pindai($student)->assertOk();

        $this->actingAs($this->operator)
            ->getJson("/api/v1/admin/attendances?session_id={$this->sesi->id}")
            ->assertStatus(403);
    }

    public function test_daftar_pemindaian_terakhir_tersedia_untuk_petugas(): void
    {
        $a = Student::factory()->create(['name' => 'Ahmad Fauzi']);
        $b = Student::factory()->create(['name' => 'Siti Aminah']);

        $this->pindai($a);
        $this->pindai($b);

        $this->actingAs($this->operator)
            ->getJson("/api/v1/scan/recent?session_id={$this->sesi->id}&limit=10")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Siti Aminah');   // terbaru di atas
    }

    public function test_konteks_scan_memuat_daftar_sesi_dan_nama_petugas(): void
    {
        $this->actingAs($this->operator)
            ->getJson('/api/v1/scan/context')
            ->assertOk()
            ->assertJsonPath('data.operator.name', $this->operator->name)
            ->assertJsonPath('data.sessions.0.id', $this->sesi->id)
            ->assertJsonPath('data.sessions.0.is_open_now', true);
    }

    /**
     * Token dikirim apa adanya oleh scanner gun; panjang/format yang meleset
     * harus ditolak validasi sebelum menyentuh database.
     */
    public function test_token_berformat_salah_ditolak_validasi(): void
    {
        $this->actingAs($this->operator)
            ->postJson('/api/v1/scan', [
                'token' => 'bukan-token',
                'attendance_session_id' => $this->sesi->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.token.0', 'Kode QR tidak dikenali.');
    }
}

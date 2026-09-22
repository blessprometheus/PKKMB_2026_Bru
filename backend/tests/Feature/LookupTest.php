<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\LookupAttempt;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Zxing\QrReader;

/**
 * Pencarian NIM publik dan unduhan nametag/QR.
 * Lihat docs/03-api-spec.md §2 dan docs/04-security.md §2–§4.
 */
class LookupTest extends TestCase
{
    use RefreshDatabase;

    private function buatMahasiswa(array $ubah = []): Student
    {
        return Student::factory()->create(array_merge([
            'nim' => '20260012345',
            'name' => 'Ahmad Fauzi',
            'faculty' => 'Fakultas Teknik',
            'study_program' => 'Teknik Informatika',
            'group_name' => 'Gugus 3',
            'birth_date' => '2007-05-14',
            'phone' => '081234567890',
            'email' => 'ahmad@contoh.test',
        ], $ubah));
    }

    public function test_maba_menemukan_datanya_dengan_nim(): void
    {
        $this->buatMahasiswa();

        $this->postJson('/api/v1/lookup', ['nim' => '20260012345'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Ahmad Fauzi')
            ->assertJsonPath('data.study_program', 'Teknik Informatika')
            ->assertJsonPath('data.group_name', 'Gugus 3')
            ->assertJsonStructure(['data' => ['downloads' => ['nametag_url', 'qr_url']]]);
    }

    /**
     * Pertahanan terpenting endpoint ini: kalaupun rate limit tertembus dan
     * seluruh NIM disapu, yang didapat penyerang hanya informasi yang toh
     * tercetak di nametag dan terlihat semua orang di lokasi acara.
     */
    public function test_data_pribadi_tidak_ikut_terkirim_ke_publik(): void
    {
        $student = $this->buatMahasiswa();

        $isi = $this->postJson('/api/v1/lookup', ['nim' => '20260012345'])->getContent();

        $this->assertStringNotContainsString('081234567890', $isi, 'Nomor HP bocor.');
        $this->assertStringNotContainsString('ahmad@contoh.test', $isi, 'Email bocor.');
        $this->assertStringNotContainsString('2007-05-14', $isi, 'Tanggal lahir bocor.');
        $this->assertStringNotContainsString($student->attendance_token, $isi, 'Token QR bocor sebagai teks.');

        foreach (['phone', 'email', 'birth_date', 'attendance_token'] as $kolom) {
            $this->assertStringNotContainsString($kolom, $isi, "Kolom {$kolom} tidak boleh muncul.");
        }
    }

    public function test_nim_tidak_ditemukan_menghasilkan_pesan_yang_membantu(): void
    {
        $this->postJson('/api/v1/lookup', ['nim' => '20269999999'])
            ->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'NIM tidak ditemukan. Pastikan NIM sesuai yang diberikan bagian akademik.',
            ]);
    }

    public function test_pencarian_dibatasi_laju_per_ip(): void
    {
        $this->buatMahasiswa();

        // Batas per menit dari config/pkkmb.php adalah 10.
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/lookup', ['nim' => '20260012345'])->assertOk();
        }

        $this->postJson('/api/v1/lookup', ['nim' => '20260012345'])
            ->assertStatus(429)
            ->assertJson(['success' => false]);
    }

    /**
     * Tabel pemantauan tidak boleh berubah menjadi salinan daftar NIM yang
     * justru bocor lewat pintu lain.
     */
    public function test_percobaan_pencarian_dicatat_dengan_nim_ter_hash(): void
    {
        $this->buatMahasiswa();

        $this->postJson('/api/v1/lookup', ['nim' => '20260012345'])->assertOk();
        $this->postJson('/api/v1/lookup', ['nim' => '20269999999'])->assertStatus(404);

        $this->assertSame(2, LookupAttempt::count());

        $catatan = LookupAttempt::pluck('nim_hash');

        foreach ($catatan as $hash) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);
            $this->assertStringNotContainsString('20260012345', $hash);
        }

        $this->assertTrue(LookupAttempt::where('found', true)->exists());
        $this->assertTrue(LookupAttempt::where('found', false)->exists());
    }

    public function test_status_kehadiran_per_sesi_ikut_ditampilkan(): void
    {
        $student = $this->buatMahasiswa();

        $sesi1 = AttendanceSession::create([
            'name' => 'Hari 1 — Pembukaan', 'event_date' => '2026-09-29',
            'starts_at' => '2026-09-29 07:00:00', 'ends_at' => '2026-09-29 12:00:00', 'is_active' => true,
        ]);

        AttendanceSession::create([
            'name' => 'Hari 2 — Materi', 'event_date' => '2026-09-30',
            'starts_at' => '2026-09-30 07:00:00', 'ends_at' => '2026-09-30 12:00:00', 'is_active' => false,
        ]);

        Attendance::create([
            'student_id' => $student->id,
            'attendance_session_id' => $sesi1->id,
            'scanned_at' => '2026-09-29 08:14:00',
            'method' => Attendance::METHOD_QR,
        ]);

        $this->postJson('/api/v1/lookup', ['nim' => '20260012345'])
            ->assertOk()
            ->assertJsonPath('data.attendance.0.session_name', 'Hari 1 — Pembukaan')
            ->assertJsonPath('data.attendance.0.status', 'hadir')
            ->assertJsonPath('data.attendance.1.status', 'belum');
    }

    // ── Unduhan ──────────────────────────────────────────────────────────

    public function test_nametag_terunduh_sebagai_pdf(): void
    {
        $this->buatMahasiswa();

        $tautan = $this->postJson('/api/v1/lookup', ['nim' => '20260012345'])
            ->json('data.downloads.nametag_url');

        $response = $this->get($tautan);

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('nametag-20260012345.pdf', $response->headers->get('Content-Disposition'));
    }

    /**
     * Ini test yang paling menentukan di modul ini: QR harus benar-benar berisi
     * token presensi. Kalau isinya salah, seluruh alur presensi hari-H gagal —
     * dan itu baru ketahuan saat 550 orang sudah mengantre.
     */
    public function test_qr_yang_diunduh_benar_benar_berisi_token_presensi(): void
    {
        $student = $this->buatMahasiswa();

        $tautan = $this->postJson('/api/v1/lookup', ['nim' => '20260012345'])
            ->json('data.downloads.qr_url');

        $response = $this->get($tautan);
        $response->assertOk()->assertHeader('Content-Type', 'image/png');

        $png = $response->getContent();

        $ukuran = getimagesizefromstring($png);
        $this->assertGreaterThanOrEqual(600, $ukuran[0], 'QR terlalu kecil untuk bertahan setelah dikompres WhatsApp.');

        $terbaca = (new QrReader($png, QrReader::SOURCE_TYPE_BLOB))->text();

        $this->assertSame($student->attendance_token, $terbaca, 'Isi QR bukan token presensi mahasiswa ini.');

        // Dan bukan NIM — NIM berurutan dan bisa ditebak siapa pun.
        $this->assertNotSame($student->nim, $terbaca);
    }

    public function test_unduhan_tanpa_tanda_tangan_ditolak(): void
    {
        $student = $this->buatMahasiswa();

        $this->getJson("/api/v1/download/nametag/{$student->id}")->assertStatus(403);
        $this->getJson("/api/v1/download/qr/{$student->id}")->assertStatus(403);
    }

    public function test_tanda_tangan_yang_diutak_atik_ditolak(): void
    {
        $this->buatMahasiswa();

        $tautan = $this->postJson('/api/v1/lookup', ['nim' => '20260012345'])
            ->json('data.downloads.nametag_url');

        // Mengubah satu karakter tanda tangan sudah cukup untuk membatalkannya.
        $rusak = substr($tautan, 0, -1).(str_ends_with($tautan, 'a') ? 'b' : 'a');

        $this->getJson($rusak)->assertStatus(403);
    }

    public function test_tautan_unduhan_kedaluwarsa_setelah_lima_belas_menit(): void
    {
        $this->buatMahasiswa();

        $tautan = $this->postJson('/api/v1/lookup', ['nim' => '20260012345'])
            ->json('data.downloads.qr_url');

        $this->get($tautan)->assertOk();

        $this->travel(16)->minutes();

        $this->getJson($tautan)->assertStatus(403);
    }

    /**
     * Tautan milik mahasiswa lain tidak boleh bisa dipakai dengan menukar id,
     * karena id ikut ditandatangani.
     */
    public function test_menukar_id_di_tautan_membatalkan_tanda_tangan(): void
    {
        $this->buatMahasiswa();
        $lain = $this->buatMahasiswa(['nim' => '20260054321', 'email' => 'lain@contoh.test']);

        $tautan = $this->postJson('/api/v1/lookup', ['nim' => '20260012345'])
            ->json('data.downloads.qr_url');

        $ditukar = preg_replace('#/qr/\d+#', "/qr/{$lain->id}", $tautan);

        $this->getJson($ditukar)->assertStatus(403);
    }

    /**
     * Saklar cadangan bila saat acara terdeteksi ada yang menyapu NIM.
     * Harus bisa dinyalakan dari .env tanpa deploy ulang.
     */
    public function test_verifikasi_tanggal_lahir_bisa_dinyalakan_dari_konfigurasi(): void
    {
        config(['pkkmb.lookup.require_birthdate' => true]);

        $this->buatMahasiswa();

        // Tanpa tanggal lahir -> gagal validasi.
        $this->postJson('/api/v1/lookup', ['nim' => '20260012345'])->assertStatus(422);

        // Tanggal lahir salah -> 404 dengan pesan yang SAMA seperti NIM tidak ada.
        $this->postJson('/api/v1/lookup', ['nim' => '20260012345', 'birth_date' => '2000-01-01'])
            ->assertStatus(404)
            ->assertJsonPath('message', 'NIM tidak ditemukan. Pastikan NIM sesuai yang diberikan bagian akademik.');

        // Tanggal lahir benar -> berhasil.
        $this->postJson('/api/v1/lookup', ['nim' => '20260012345', 'birth_date' => '2007-05-14'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Ahmad Fauzi');
    }

    public function test_nim_dengan_karakter_aneh_ditolak_sebelum_menyentuh_database(): void
    {
        $this->postJson('/api/v1/lookup', ['nim' => "' OR 1=1 --"])
            ->assertStatus(422)
            ->assertJsonPath('errors.nim.0', 'NIM hanya boleh berisi huruf, angka, titik, dan strip.');
    }
}

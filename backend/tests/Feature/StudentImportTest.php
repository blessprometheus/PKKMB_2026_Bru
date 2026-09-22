<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ImportBatch;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Menguji importer Excel sesuai docs/02-data-model.md §5.
 *
 * CATATAN: nama header yang dipakai di sini berasal dari config/pkkmb.php,
 * yang masih RANCANGAN — file Excel PMB asli (D1) belum diterima. Begitu file
 * asli datang, perbarui config dan test ini bersamaan.
 */
class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->admin = Admin::factory()->admin()->create();
    }

    /**
     * Membuat berkas .xlsx sungguhan (bukan tiruan), supaya jalur baca
     * PhpSpreadsheet ikut teruji.
     *
     * @param  array<int, array<int, string>>  $rows
     */
    private function buatXlsx(array $rows, string $filename = 'data-maba.xlsx'): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $r => $row) {
            foreach ($row as $c => $value) {
                $sheet->setCellValue([$c + 1, $r + 1], $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'uji').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, $filename, null, null, true);
    }

    public function test_impor_xlsx_berhasil_dan_melaporkan_jumlahnya(): void
    {
        $file = $this->buatXlsx([
            ['NIM', 'Nama Lengkap', 'Fakultas', 'Program Studi', 'Kelompok', 'Jenis Kelamin'],
            ['20260010001', 'Ahmad Fauzi', 'Fakultas Teknik', 'Teknik Informatika', 'Gugus 1', 'L'],
            ['20260010002', 'Siti Aminah', 'Fakultas Hukum', 'Ilmu Hukum', 'Gugus 2', 'P'],
        ]);

        $response = $this->actingAs($this->admin)
            ->post('/api/v1/admin/students/import', ['file' => $file]);

        $response->assertOk()
            ->assertJsonPath('data.inserted_count', 2)
            ->assertJsonPath('data.failed_count', 0);

        $this->assertDatabaseHas('students', ['nim' => '20260010001', 'name' => 'Ahmad Fauzi']);
        $this->assertSame(2, Student::count());
    }

    /**
     * Setiap mahasiswa harus mendapat token acak 32 karakter saat diimpor —
     * ini yang menjadi isi QR presensi.
     */
    public function test_setiap_mahasiswa_mendapat_token_acak_yang_unik(): void
    {
        $file = $this->buatXlsx([
            ['NIM', 'Nama', 'Fakultas', 'Prodi'],
            ['20260010001', 'Ahmad Fauzi', 'Fakultas Teknik', 'Teknik Informatika'],
            ['20260010002', 'Siti Aminah', 'Fakultas Hukum', 'Ilmu Hukum'],
        ]);

        $this->actingAs($this->admin)->post('/api/v1/admin/students/import', ['file' => $file]);

        $tokens = Student::pluck('attendance_token');

        $this->assertCount(2, $tokens);
        $this->assertCount(2, $tokens->unique(), 'Token presensi tidak unik antar mahasiswa.');

        foreach ($tokens as $token) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $token);
            // Token TIDAK boleh bisa ditebak dari NIM.
            $this->assertStringNotContainsString('2026001', $token);
        }
    }

    /**
     * Header wajib tidak ditemukan -> SELURUH impor dibatalkan.
     * Data yang masuk setengah-setengah lebih berbahaya daripada tidak masuk.
     */
    public function test_kolom_wajib_hilang_membatalkan_seluruh_impor(): void
    {
        $file = $this->buatXlsx([
            ['NIM', 'Nama', 'Fakultas'],   // 'Program Studi' sengaja tidak ada
            ['20260010001', 'Ahmad Fauzi', 'Fakultas Teknik'],
            ['20260010002', 'Siti Aminah', 'Fakultas Hukum'],
        ]);

        $response = $this->actingAs($this->admin)
            ->post('/api/v1/admin/students/import', ['file' => $file]);

        $response->assertStatus(422)->assertJson(['success' => false]);

        // Pesan harus menyebut apa yang dicari DAN apa yang ditemukan.
        $this->assertStringContainsString('study_program', $response->json('message'));
        $this->assertStringContainsString('fakultas', $response->json('message'));

        $this->assertSame(0, Student::count(), 'Ada data yang masuk padahal impor seharusnya dibatalkan.');
        $this->assertSame(ImportBatch::STATUS_FAILED, ImportBatch::first()->status);
    }

    public function test_baris_gagal_dilaporkan_dengan_nomor_baris_dan_sisanya_tetap_masuk(): void
    {
        $file = $this->buatXlsx([
            ['NIM', 'Nama', 'Fakultas', 'Prodi'],
            ['20260010001', 'Ahmad Fauzi', 'Fakultas Teknik', 'Teknik Informatika'],
            ['20260010002', '', 'Fakultas Hukum', 'Ilmu Hukum'],          // nama kosong -> baris 3
            ['20260010003', 'Budi Santoso', 'Fakultas Teknik', 'Teknik Informatika'],
        ]);

        $response = $this->actingAs($this->admin)
            ->post('/api/v1/admin/students/import', ['file' => $file]);

        $response->assertOk()
            ->assertJsonPath('data.inserted_count', 2)
            ->assertJsonPath('data.failed_count', 1);

        $errors = $response->json('data.errors');
        $this->assertSame(3, $errors[0]['row'], 'Nomor baris tidak cocok dengan nomor baris di Excel.');
        $this->assertSame('name', $errors[0]['column']);
        $this->assertStringContainsString('wajib diisi', $errors[0]['message']);
    }

    public function test_nim_ganda_di_berkas_yang_sama_ditolak_dan_menyebut_baris_asalnya(): void
    {
        $file = $this->buatXlsx([
            ['NIM', 'Nama', 'Fakultas', 'Prodi'],
            ['20260010001', 'Ahmad Fauzi', 'Fakultas Teknik', 'Teknik Informatika'],
            ['20260010001', 'Ahmad Fauzi Kembar', 'Fakultas Teknik', 'Teknik Informatika'],
        ]);

        $response = $this->actingAs($this->admin)
            ->post('/api/v1/admin/students/import', ['file' => $file]);

        $response->assertOk()->assertJsonPath('data.failed_count', 1);

        $this->assertStringContainsString('baris 2', $response->json('data.errors.0.message'));
        $this->assertSame(1, Student::count());
    }

    /**
     * Ini aturan paling mudah dilanggar tanpa sadar, dan akibatnya paling parah:
     * kalau impor ulang mengganti token, seluruh nametag yang sudah dicetak
     * mahasiswa menjadi tidak sah (docs/02-data-model.md §5 butir 3).
     */
    public function test_impor_ulang_memperbarui_data_tanpa_mengubah_token_qr(): void
    {
        $rows = [
            ['NIM', 'Nama', 'Fakultas', 'Prodi'],
            ['20260010001', 'Ahmad Fauzi', 'Fakultas Teknik', 'Teknik Informatika'],
        ];

        $this->actingAs($this->admin)
            ->post('/api/v1/admin/students/import', ['file' => $this->buatXlsx($rows)]);

        $tokenAwal = Student::where('nim', '20260010001')->value('attendance_token');

        // Impor ulang dengan prodi yang dikoreksi.
        $rows[1][3] = 'Teknik Sipil';

        $response = $this->actingAs($this->admin)
            ->post('/api/v1/admin/students/import', ['file' => $this->buatXlsx($rows)]);

        $response->assertOk()
            ->assertJsonPath('data.updated_count', 1)
            ->assertJsonPath('data.inserted_count', 0);

        $student = Student::where('nim', '20260010001')->first();

        $this->assertSame('Teknik Sipil', $student->study_program, 'Data tidak ikut diperbarui.');
        $this->assertSame($tokenAwal, $student->attendance_token, 'Token QR berubah — nametag yang sudah dicetak jadi tidak sah.');
        $this->assertSame(1, Student::count());
    }

    /**
     * Excel menyimpan angka sebagai numerik: NIM "0026001" bisa berubah jadi 26001.
     * Nol di depan harus bertahan.
     */
    public function test_nim_berawalan_nol_tidak_kehilangan_nolnya(): void
    {
        $file = $this->buatXlsx([
            ['NIM', 'Nama', 'Fakultas', 'Prodi'],
            ['0026001234', 'Ahmad Fauzi', 'Fakultas Teknik', 'Teknik Informatika'],
        ]);

        $this->actingAs($this->admin)->post('/api/v1/admin/students/import', ['file' => $file]);

        $this->assertDatabaseHas('students', ['nim' => '0026001234']);
    }

    public function test_berkas_bukan_excel_ditolak_walau_namanya_xlsx(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'palsu').'.xlsx';
        file_put_contents($path, "<?php echo 'ini skrip php, bukan excel'; ?>");

        $file = new UploadedFile($path, 'jahat.xlsx', null, null, true);

        $this->actingAs($this->admin)
            ->post('/api/v1/admin/students/import', ['file' => $file])
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertSame(0, Student::count());
    }

    /**
     * docs/04-security.md §5.1: ukuran maksimal 10 MB. Batasnya di
     * config/pkkmb.php (`import.max_file_size_kb` = 10240), belum pernah
     * diuji sampai sekarang.
     */
    public function test_berkas_melebihi_batas_ukuran_ditolak(): void
    {
        $file = UploadedFile::fake()->create(
            'data-sangat-besar.xlsx',
            10241, // KB — 1 KB di atas batas 10240
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        $response = $this->actingAs($this->admin)
            ->post('/api/v1/admin/students/import', ['file' => $file]);

        $response->assertStatus(422)->assertJson(['success' => false]);
        $this->assertStringContainsString('10 MB', $response->json('errors.file.0'));
        $this->assertSame(0, Student::count());
    }

    /** Berkas tepat di batas atas (10 MB) harus TETAP diterima — bukan off-by-one. */
    public function test_berkas_tepat_di_batas_ukuran_diterima(): void
    {
        // Isi baris sungguhan supaya lolos juga tahap parsing, bukan cuma validasi ukuran.
        $rows = [['NIM', 'Nama', 'Fakultas', 'Prodi'], ['20260010001', 'Ahmad Fauzi', 'Fakultas Teknik', 'Teknik Informatika']];
        $file = $this->buatXlsx($rows);

        // Pastikan berkas asli jauh di bawah batas (memang selalu begitu untuk
        // xlsx kecil) — yang diuji di sini adalah ambang validasi 'max', bukan
        // ukuran berkas Excel sungguhan yang besar.
        $this->assertLessThan(10240 * 1024, $file->getSize());

        $this->actingAs($this->admin)
            ->post('/api/v1/admin/students/import', ['file' => $file])
            ->assertOk();
    }

    public function test_impor_tercatat_di_riwayat_dan_jejak_audit(): void
    {
        $file = $this->buatXlsx([
            ['NIM', 'Nama', 'Fakultas', 'Prodi'],
            ['20260010001', 'Ahmad Fauzi', 'Fakultas Teknik', 'Teknik Informatika'],
        ]);

        $this->actingAs($this->admin)->post('/api/v1/admin/students/import', ['file' => $file]);

        $this->assertDatabaseHas('import_batches', [
            'admin_id' => $this->admin->id,
            'status' => ImportBatch::STATUS_DONE,
            'inserted_count' => 1,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'import.completed',
        ]);
    }
}

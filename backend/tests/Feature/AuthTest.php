<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

/**
 * Menguji perilaku autentikasi yang dijanjikan docs/04-security.md §1.
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sanctum hanya menyalakan sesi bila permintaan datang dari domain yang
     * terdaftar di SANCTUM_STATEFUL_DOMAINS, dicocokkan lewat header Origin.
     * Test harus meniru itu, kalau tidak yang teruji bukan jalur yang dipakai
     * frontend sungguhan.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Origin', 'http://localhost:3000');
    }

    /**
     * Salah konfigurasi domain harus menghasilkan pesan yang menjelaskan sebabnya,
     * bukan 500 "Session store not set on request" yang tidak berarti apa-apa
     * bagi orang yang sedang men-deploy jam dua pagi.
     */
    public function test_permintaan_dari_domain_tak_terdaftar_dapat_pesan_yang_jelas(): void
    {
        Admin::factory()->admin()->create([
            'email' => 'panitia@uninus.test',
            'password' => 'kata-sandi-panjang-123',
        ]);

        $response = $this->withHeader('Origin', 'https://situs-asing.example')
            ->postJson('/api/v1/auth/login', [
                'email' => 'panitia@uninus.test',
                'password' => 'kata-sandi-panjang-123',
            ]);

        $response->assertStatus(400)->assertJson(['success' => false]);
        $this->assertStringContainsString('SANCTUM_STATEFUL_DOMAINS', $response->json('message'));
        $this->assertGuest();
    }

    public function test_admin_bisa_masuk_dengan_kredensial_benar(): void
    {
        $admin = Admin::factory()->admin()->create([
            'email' => 'panitia@uninus.test',
            'password' => 'kata-sandi-panjang-123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'panitia@uninus.test',
            'password' => 'kata-sandi-panjang-123',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.role', 'admin');

        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull($admin->fresh()->last_login_at);
    }

    /**
     * Pesan gagal harus SERAGAM: tidak boleh membedakan "email tidak terdaftar"
     * dari "kata sandi salah". Pembedaannya hanya berguna bagi penebak akun.
     */
    public function test_pesan_gagal_tidak_membocorkan_email_mana_yang_terdaftar(): void
    {
        Admin::factory()->create(['email' => 'ada@uninus.test', 'password' => 'benar-sekali-123']);

        $salahSandi = $this->postJson('/api/v1/auth/login', [
            'email' => 'ada@uninus.test',
            'password' => 'salah-total-123',
        ]);

        $emailTidakAda = $this->postJson('/api/v1/auth/login', [
            'email' => 'tidakada@uninus.test',
            'password' => 'salah-total-123',
        ]);

        $salahSandi->assertStatus(422);
        $emailTidakAda->assertStatus(422);

        $this->assertSame(
            $salahSandi->json('errors.email'),
            $emailTidakAda->json('errors.email'),
            'Pesan gagal login berbeda antara email terdaftar dan tidak terdaftar.',
        );

        $this->assertSame('Email atau kata sandi salah.', $salahSandi->json('errors.email.0'));
    }

    public function test_akun_nonaktif_ditolak_meski_kata_sandi_benar(): void
    {
        Admin::factory()->nonaktif()->create([
            'email' => 'cuti@uninus.test',
            'password' => 'kata-sandi-panjang-123',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'cuti@uninus.test',
            'password' => 'kata-sandi-panjang-123',
        ])->assertStatus(403)
            ->assertJson(['success' => false]);

        $this->assertGuest();
    }

    public function test_percobaan_login_dibatasi_per_ip(): void
    {
        Admin::factory()->create(['email' => 'target@uninus.test', 'password' => 'benar-sekali-123']);

        // Lima percobaan pertama ditolak biasa, yang keenam kena rate limit.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'target@uninus.test',
                'password' => 'tebakan-salah-'.$i,
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'target@uninus.test',
            'password' => 'tebakan-salah-lagi',
        ])->assertStatus(429);
    }

    /**
     * Penguncian PER AKUN diuji terpisah dari rate limit per IP — keduanya
     * lapisan berbeda dan harus terbukti masing-masing.
     */
    public function test_akun_terkunci_setelah_delapan_kegagalan_beruntun(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);

        Admin::factory()->create(['email' => 'incar@uninus.test', 'password' => 'benar-sekali-123']);

        for ($i = 0; $i < 8; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'incar@uninus.test',
                'password' => 'salah-'.$i,
            ])->assertStatus(422);
        }

        // Percobaan ke-9 ditolak walaupun kata sandinya BENAR.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'incar@uninus.test',
            'password' => 'benar-sekali-123',
        ])->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_endpoint_admin_menolak_tamu(): void
    {
        $this->getJson('/api/v1/admin/students')
            ->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    /**
     * Test regresi untuk bug yang ditemukan saat menyusun Fase 6.
     *
     * `getJson()`/`postJson()` SELALU menyertakan header `Accept: application/
     * json` secara otomatis — jadi tidak pernah bisa menangkap bug ini.
     * Test di atas memakai helper polos `get()` yang TIDAK menambahkan header
     * itu, meniru klien nyata yang tidak mengirimnya (curl polos, Postman
     * default, atau skrip pihak ketiga mana pun).
     *
     * Root cause: `ApplicationBuilder::withMiddleware()` Laravel SELALU
     * mendaftarkan `redirectGuestsTo(fn () => route('login'))` sebelum
     * `bootstrap/app.php` sempat menimpanya. Karena API ini tidak punya rute
     * bernama `login`, `Authenticate::unauthenticated()` melempar
     * RouteNotFoundException saat `expectsJson()` bernilai false — bocor
     * sebagai 500 mentah, melewati seluruh penanganan error kustom.
     * Diperbaiki dengan `$middleware->redirectGuestsTo(fn () => null)` di
     * bootstrap/app.php.
     */
    public function test_endpoint_admin_menolak_tamu_tanpa_header_accept(): void
    {
        $this->get('/api/v1/admin/students')
            ->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    /**
     * Inti pembatasan peran: operator harus mendapat 403, bukan sekadar tidak
     * melihat menunya di frontend (docs/04-security.md checklist §11 butir 9).
     */
    public function test_operator_tidak_bisa_membuka_daftar_mahasiswa(): void
    {
        Student::factory()->count(3)->create();
        $operator = Admin::factory()->create(['role' => Admin::ROLE_OPERATOR]);

        $this->actingAs($operator)
            ->getJson('/api/v1/admin/students')
            ->assertStatus(403)
            ->assertJson(['success' => false]);
    }

    public function test_operator_tidak_bisa_mengimpor_data(): void
    {
        $operator = Admin::factory()->create(['role' => Admin::ROLE_OPERATOR]);

        $this->actingAs($operator)
            ->postJson('/api/v1/admin/students/import')
            ->assertStatus(403);
    }

    public function test_me_mengembalikan_profil_tanpa_kata_sandi(): void
    {
        $admin = Admin::factory()->admin()->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/auth/me');

        $response->assertOk()->assertJsonPath('data.email', $admin->email);
        $this->assertStringNotContainsString('password', $response->getContent());
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Autentikasi panitia. Lihat docs/03-api-spec.md §3 dan docs/04-security.md §1.
 *
 * Mode cookie SPA (Sanctum), bukan token bearer: frontend dan API same-origin,
 * jadi sesi disimpan di cookie HttpOnly yang tidak bisa dibaca JavaScript.
 */
class AuthController extends Controller
{
    /** Kunci akun setelah sekian kegagalan berturut-turut. */
    private const MAX_ACCOUNT_ATTEMPTS = 8;

    private const LOCKOUT_SECONDS = 900; // 15 menit

    /**
     * Hash bcrypt SAH dari nilai acak yang tidak pernah dipakai siapa pun.
     *
     * Dipakai untuk menyamakan waktu respons saat email tidak terdaftar: tanpa ini,
     * permintaan dengan email tak dikenal selesai jauh lebih cepat daripada email
     * terdaftar, dan selisih waktunya sendiri membocorkan email mana yang ada.
     *
     * Harus hash yang benar-benar valid — string asal-asalan membuat Hash::check
     * melempar "This password does not use the Bcrypt algorithm" dan berakhir 500.
     */
    private const DUMMY_HASH = '$2y$12$V4sgkTo8hNreoi2IxDD9zOvH8AWoM35FkkpXGUEjGYvB/sDV/EyfG';

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email:rfc', 'max:160'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        /*
         * Login berbasis cookie butuh sesi. Sanctum hanya menyalakan sesi bila
         * permintaan datang dari domain yang terdaftar di SANCTUM_STATEFUL_DOMAINS
         * (dicocokkan lewat header Origin/Referer).
         *
         * Tanpa penjagaan ini, permintaan dari domain yang tidak terdaftar berakhir
         * sebagai 500 "Session store not set on request" — error yang tidak
         * menjelaskan apa pun. Pesan di bawah menyebut sebab yang sebenarnya,
         * supaya salah konfigurasi ketahuan saat deploy, bukan saat hari-H.
         */
        if (! $request->hasSession()) {
            return ApiResponse::error(
                'Permintaan tidak berasal dari alamat frontend yang terdaftar, '.
                'sehingga sesi tidak dapat dibuat. Periksa SANCTUM_STATEFUL_DOMAINS pada konfigurasi server.',
                400,
            );
        }

        $accountKey = 'login-account:'.Str::lower($credentials['email']);

        // Penguncian per akun. Rate limit per IP ditangani middleware `throttle`
        // di routes/api.php — keduanya diperlukan: satu melindungi dari penebakan
        // satu akun, satunya dari satu sumber yang menyapu banyak akun.
        if (RateLimiter::tooManyAttempts($accountKey, self::MAX_ACCOUNT_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($accountKey);

            return ApiResponse::error(
                "Akun terkunci sementara karena terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.",
                429,
            )->header('Retry-After', $seconds);
        }

        $admin = Admin::where('email', $credentials['email'])->first();

        // Hash::check tetap dijalankan walau admin tidak ada, memakai hash palsu,
        // supaya waktu respons tidak membocorkan email mana yang terdaftar.
        $passwordOk = $admin !== null
            ? Hash::check($credentials['password'], $admin->password)
            : Hash::check($credentials['password'], self::DUMMY_HASH);

        if ($admin === null || ! $passwordOk) {
            RateLimiter::hit($accountKey, self::LOCKOUT_SECONDS);

            // Pesan sengaja seragam: tidak membedakan email tidak terdaftar
            // dari kata sandi salah (docs/04-security.md §1).
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if (! $admin->is_active) {
            return ApiResponse::error('Akun Anda tidak aktif. Hubungi admin.', 403);
        }

        RateLimiter::clear($accountKey);

        Auth::guard('web')->login($admin, remember: false);

        // Wajib: mencegah session fixation — penyerang yang sempat menanam
        // id sesi sebelum login tidak ikut terbawa masuk.
        $request->session()->regenerate();

        $admin->forceFill(['last_login_at' => now()])->save();

        AuditLogger::record('auth.login', 'admin', $admin->id, request: $request);

        return ApiResponse::ok($this->profile($admin));
    }

    public function logout(Request $request): JsonResponse
    {
        $admin = $request->user();

        if ($admin !== null) {
            AuditLogger::record('auth.logout', 'admin', $admin->id, request: $request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::ok(['message' => 'Anda telah keluar.']);
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::ok($this->profile($request->user()));
    }

    /** @return array<string, mixed> */
    private function profile(Admin $admin): array
    {
        return [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => $admin->role,
        ];
    }
}

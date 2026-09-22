<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pembatas peran. Dipakai sebagai `role:admin` atau `role:admin,operator`.
 *
 * Ini yang benar-benar mengamankan — menyembunyikan menu di frontend bukan
 * pengamanan. Operator harus mendapat 403, bukan sekadar tidak melihat tautannya.
 * Lihat docs/04-security.md §1 dan checklist §11 butir 9.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $admin = $request->user();

        if ($admin === null) {
            return ApiResponse::error('Anda harus masuk terlebih dahulu.', 401);
        }

        // Akun yang dinonaktifkan tidak boleh lanjut meski sesinya masih hidup —
        // mis. petugas yang aksesnya dicabut di tengah acara.
        if (! $admin->is_active) {
            return ApiResponse::error('Akun Anda tidak aktif. Hubungi admin.', 403);
        }

        if ($roles !== [] && ! in_array($admin->role, $roles, true)) {
            return ApiResponse::error('Anda tidak berhak mengakses bagian ini.', 403);
        }

        return $next($request);
    }
}

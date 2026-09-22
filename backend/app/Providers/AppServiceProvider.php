<?php

namespace App\Providers;

use App\Support\ApiResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->daftarkanBatasLaju();
    }

    /**
     * Batas laju endpoint publik. Lihat docs/04-security.md §2.
     *
     * Dua lapis sengaja dipakai bersama:
     * - per menit menahan skrip yang menyapu cepat;
     * - per jam menahan penyapuan pelan yang sengaja dibuat di bawah batas menitan.
     *
     * Keduanya memakai IP. Di balik Nginx, ini hanya benar bila `TRUSTED_PROXIES`
     * disetel — tanpa itu seluruh pengunjung terlihat sebagai satu IP dan batas
     * ini justru mengunci semua orang sekaligus (docs/06-deployment.md §3).
     */
    private function daftarkanBatasLaju(): void
    {
        RateLimiter::for('lookup', function (Request $request) {
            $pesan = 'Terlalu banyak percobaan pencarian. Coba lagi dalam beberapa menit.';

            $tolak = fn () => ApiResponse::error($pesan, 429);

            return [
                Limit::perMinute((int) config('pkkmb.lookup.per_minute'))
                    ->by($request->ip())
                    ->response($tolak),
                Limit::perHour((int) config('pkkmb.lookup.per_hour'))
                    ->by($request->ip())
                    ->response($tolak),
            ];
        });
    }
}

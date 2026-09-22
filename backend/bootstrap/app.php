<?php

use App\Http\Middleware\EnsureRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum mode cookie SPA same-origin: frontend & API satu domain,
        // jadi sesi memakai cookie HttpOnly, bukan token di localStorage.
        // Lihat docs/03-api-spec.md §1.3 dan docs/04-security.md §7.
        $middleware->statefulApi();

        $middleware->alias([
            'role' => EnsureRole::class,
        ]);

        // Nginx di depan aplikasi. Tanpa ini SELURUH rate limit melihat semua
        // pengunjung sebagai satu IP (IP proxy) dan perlindungan di
        // docs/04-security.md §2 menjadi tidak berguna.
        $middleware->trustProxies(at: explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1')));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
         * Seluruh error API mengikuti satu bentuk: { success:false, message, errors? }
         * dengan pesan Bahasa Indonesia (docs/03-api-spec.md §1.1).
         * Tanpa ini, Laravel mengembalikan bentuk berbeda-beda per jenis error
         * dan frontend harus menebak-nebak.
         */
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            [$status, $message, $errors] = match (true) {
                $e instanceof ValidationException => [
                    422, 'Data yang dikirim tidak valid.', $e->errors(),
                ],
                $e instanceof AuthenticationException => [
                    401, 'Anda harus masuk terlebih dahulu.', null,
                ],
                $e instanceof AuthorizationException => [
                    403, 'Anda tidak berhak mengakses bagian ini.', null,
                ],
                $e instanceof ThrottleRequestsException => [
                    429, 'Terlalu banyak permintaan. Coba lagi dalam beberapa saat.', null,
                ],
                $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => [
                    404, 'Data yang diminta tidak ditemukan.', null,
                ],
                default => [null, null, null],
            };

            if ($status === null) {
                // Error tak terduga. Di produksi JANGAN bocorkan detail internal;
                // di lokal tetap tampilkan agar bisa diperbaiki.
                $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

                $message = config('app.debug')
                    ? $e->getMessage()
                    : 'Terjadi kesalahan pada server. Silakan coba lagi.';
            }

            $payload = ['success' => false, 'message' => $message];

            if ($errors !== null) {
                $payload['errors'] = $errors;
            }

            $response = response()->json($payload, $status);

            if ($e instanceof ThrottleRequestsException && $retry = $e->getHeaders()['Retry-After'] ?? null) {
                $response->header('Retry-After', $retry);
            }

            return $response;
        });
    })->create();

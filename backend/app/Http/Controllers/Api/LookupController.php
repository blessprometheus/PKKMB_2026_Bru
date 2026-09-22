<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\LookupAttempt;
use App\Models\Student;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Pencarian data diri oleh mahasiswa baru. TANPA LOGIN.
 * Lihat docs/03-api-spec.md §2.1 dan docs/04-security.md §2.
 *
 * Ini satu-satunya endpoint publik yang menyentuh data pribadi, jadi ia juga
 * permukaan serang terbesar proyek ini. Tiga pertahanan bekerja bersama:
 *
 *   1. Rate limit berlapis (10/menit dan 40/jam per IP) — dipasang di routes.
 *   2. Minimisasi field: yang dikembalikan hanya informasi yang toh tercetak di
 *      nametag dan terlihat semua orang di lokasi acara.
 *   3. Pencatatan percobaan (NIM di-hash) agar penyapuan bisa terdeteksi.
 *
 * Pertahanan nomor 2 yang paling penting: kalaupun rate limit tertembus, yang
 * didapat penyerang bukan data yang merugikan.
 */
class LookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $wajibTanggalLahir = (bool) config('pkkmb.lookup.require_birthdate');

        $rules = [
            'nim' => ['required', 'string', 'max:30', 'regex:/^[0-9A-Za-z.\-]+$/'],
        ];

        // Saklar cadangan: bisa dinyalakan lewat .env tanpa deploy ulang kalau
        // saat acara terdeteksi ada yang menyapu NIM (docs/04-security.md §2).
        if ($wajibTanggalLahir) {
            $rules['birth_date'] = ['required', 'date'];
        }

        $data = $request->validate($rules, [
            'nim.regex' => 'NIM hanya boleh berisi huruf, angka, titik, dan strip.',
        ]);

        $nim = trim($data['nim']);

        $student = Student::where('nim', $nim)->first();

        if ($student !== null && $wajibTanggalLahir) {
            $cocok = $student->birth_date?->toDateString() === date('Y-m-d', strtotime($data['birth_date']));

            if (! $cocok) {
                $student = null;
            }
        }

        LookupAttempt::create([
            'ip' => $request->ip(),
            'nim_hash' => LookupAttempt::hashNim($nim),
            'found' => $student !== null,
        ]);

        if ($student === null) {
            // Pesan seragam: tidak membedakan "NIM tidak ada" dari "tanggal lahir
            // tidak cocok". Pembedaannya hanya berguna bagi orang yang sedang
            // menebak-nebak (docs/04-security.md §2 butir 4).
            return ApiResponse::error(
                'NIM tidak ditemukan. Pastikan NIM sesuai yang diberikan bagian akademik.',
                404,
            );
        }

        return ApiResponse::ok([
            // toPublicArray() adalah daftar-putih: kolom baru berisi data pribadi
            // tidak akan otomatis ikut bocor lewat sini.
            ...$student->toPublicArray(),
            'attendance' => $this->riwayatKehadiran($student),
            'downloads' => [
                'nametag_url' => $this->tautanUnduhan('unduh.nametag', $student),
                'qr_url' => $this->tautanUnduhan('unduh.qr', $student),
            ],
        ]);
    }

    /**
     * Status kehadiran per sesi, supaya mahasiswa bisa memastikan absennya
     * benar-benar tercatat — ini mengurangi antrean pertanyaan ke panitia.
     *
     * @return array<int, array<string, mixed>>
     */
    private function riwayatKehadiran(Student $student): array
    {
        $sessions = AttendanceSession::orderBy('starts_at')->get();

        if ($sessions->isEmpty()) {
            return [];
        }

        $hadir = $student->attendances()->pluck('scanned_at', 'attendance_session_id');

        return $sessions->map(fn (AttendanceSession $s) => [
            'session_name' => $s->name,
            'status' => $hadir->has($s->id) ? 'hadir' : 'belum',
            'scanned_at' => $hadir->get($s->id)?->toIso8601String(),
        ])->all();
    }

    /**
     * Tautan unduhan bertanda tangan, berlaku 15 menit.
     *
     * `id` memang tampak di URL, dan itu tidak apa-apa: tanda tangannya yang
     * membuat URL tidak bisa dikarang. Tanpa tanda tangan, pola seperti
     * /unduh/nametag/1, /2, /3 bisa disapu satu angkatan dengan skrip sederhana
     * (docs/04-security.md §4).
     */
    private function tautanUnduhan(string $routeName, Student $student): string
    {
        return URL::temporarySignedRoute($routeName, now()->addMinutes(15), ['student' => $student->id]);
    }
}

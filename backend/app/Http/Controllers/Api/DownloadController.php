<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\NametagService;
use App\Services\QrCodeService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unduhan nametag dan QR. Lihat docs/03-api-spec.md §2.2–2.3.
 *
 * Kedua rute dilindungi middleware `signed` — tanda tangan kedaluwarsa 15 menit.
 * Berkas dibuat saat diminta dan langsung dialirkan; TIDAK ADA berkas nametag
 * atau QR yang tersimpan di disk dengan nama yang bisa ditebak
 * (docs/04-security.md §4).
 */
class DownloadController extends Controller
{
    public function nametag(Student $student, NametagService $nametag): Response
    {
        return response($nametag->pdfFor($student), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="nametag-'.$this->namaAman($student).'.pdf"',
            // Tautan berumur pendek; jangan sampai proxy atau CDN menyimpannya.
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function qr(Student $student, QrCodeService $qrCode): Response
    {
        return response($qrCode->pngFor($student), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="qr-presensi-'.$this->namaAman($student).'.png"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    /**
     * NIM boleh muncul di nama berkas — berkasnya mendarat di perangkat
     * mahasiswa itu sendiri. Tetap disaring supaya karakter aneh tidak
     * merusak header Content-Disposition.
     */
    private function namaAman(Student $student): string
    {
        return preg_replace('/[^0-9A-Za-z.\-]/', '', $student->nim) ?: 'peserta';
    }
}

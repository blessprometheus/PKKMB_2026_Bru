<?php

namespace App\Services;

use App\Models\Student;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Pembuat QR presensi. Lihat docs/04-security.md §3.3.
 *
 * Isi QR adalah `attendance_token` — 32 karakter acak, BUKAN NIM. QR dibuat
 * **di server**, tidak pernah di browser, supaya token tidak pernah singgah
 * di JavaScript klien.
 */
class QrCodeService
{
    /**
     * Ukuran sisi QR dalam piksel.
     *
     * Docs mensyaratkan PNG minimal 600×600 agar tetap terbaca setelah
     * dikompres WhatsApp — jalur distribusi yang hampir pasti dipakai mahasiswa.
     */
    private const SIZE = 600;

    /**
     * Margin (quiet zone) dalam piksel.
     *
     * Spesifikasi QR mensyaratkan quiet zone minimal 4 modul. Token 32 karakter
     * pada level Quartile menghasilkan sekitar 33×33 modul, jadi satu modul
     * ≈ 18 px dan 4 modul ≈ 72 px. 80 px memberi sedikit kelonggaran.
     * QR tanpa quiet zone cukup sering gagal dipindai — dan kegagalannya
     * terjadi di pintu masuk dengan antrean di belakangnya.
     */
    private const MARGIN = 80;

    /** Mengembalikan isi berkas PNG sebagai string biner. */
    public function pngFor(Student $student): string
    {
        return $this->build($student->attendance_token)->getString();
    }

    /**
     * Data URI untuk ditanam langsung di PDF nametag.
     *
     * Ditanam sebagai data URI, bukan berkas di disk, supaya tidak ada gambar QR
     * yang tertinggal di server dengan nama yang bisa ditebak
     * (docs/04-security.md §4).
     */
    public function dataUriFor(Student $student): string
    {
        return $this->build($student->attendance_token)->getDataUri();
    }

    private function build(string $token): \Endroid\QrCode\Writer\Result\ResultInterface
    {
        return (new Builder(
            writer: new PngWriter,
            writerOptions: [],
            validateResult: false,
            data: $token,
            encoding: new Encoding('UTF-8'),
            // Quartile (25%) dipilih di atas batas minimum M: nametag akan
            // terlipat, tertekuk, dan basah kena hujan sebelum sampai pintu masuk.
            errorCorrectionLevel: ErrorCorrectionLevel::Quartile,
            size: self::SIZE,
            margin: self::MARGIN,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ))->build();
    }
}

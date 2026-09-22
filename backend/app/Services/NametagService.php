<?php

namespace App\Services;

use App\Models\Student;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;

/**
 * Pembuat nametag PDF siap cetak. Lihat docs/05-frontend-spec.md §6.
 *
 * QR menyatu di dalam nametag, bukan berkas terpisah — itu mitigasi L1 terhadap
 * titipan absen (docs/04-security.md §3.2): yang ditunjukkan ke petugas adalah
 * kartu utuh bernama besar, bukan sekadar kotak hitam-putih.
 */
class NametagService
{
    public function __construct(private readonly QrCodeService $qrCode) {}

    /** Mengembalikan isi berkas PDF sebagai string biner. */
    public function pdfFor(Student $student): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);   // tidak ada aset dari luar; QR ditanam sebagai data URI
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);

        $html = View::make('nametag', [
            'student' => $student,
            'qrDataUri' => $this->qrCode->dataUriFor($student),
            'ukuranNama' => $this->ukuranFontNama($student->name),
            'logoUninus' => $this->logoDataUri('logo-uninus.png'),
            'logoPkkmb' => $this->logoDataUri('logo-pkkmb.png'),
        ])->render();

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper([0, 0, 297.64, 419.53], 'portrait');   // A6 dalam titik (105 × 148 mm)
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * Logo ditanam sebagai data URI, bukan dirujuk lewat path.
     *
     * `isRemoteEnabled` sengaja dimatikan agar PDF tidak pernah mengambil aset
     * dari luar; menanam berkasnya langsung membuat nametag tetap utuh tanpa
     * melonggarkan aturan itu.
     *
     * Mengembalikan string kosong bila berkas belum ada — nametag tetap tercetak
     * tanpa logo, bukan gagal total. Lihat docs/01-prd.md §8 (D5).
     */
    private function logoDataUri(string $namaBerkas): string
    {
        $path = resource_path('images/'.$namaBerkas);

        if (! is_file($path)) {
            return '';
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
    }

    /**
     * Mengecilkan ukuran huruf untuk nama panjang.
     *
     * Alternatifnya adalah memotong nama, dan nama orang yang terpotong di
     * tanda pengenal resmi universitas adalah kesalahan yang terlihat oleh
     * semua orang di lokasi acara.
     */
    private function ukuranFontNama(string $nama): float
    {
        $panjang = mb_strlen($nama);

        return match (true) {
            $panjang <= 18 => 17,
            $panjang <= 26 => 14,
            $panjang <= 34 => 11.5,
            default => 9.5,
        };
    }
}

<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Pembuat berkas Excel untuk rekap dan daftar mahasiswa.
 * Lihat docs/04-security.md §5.3.
 *
 * ⚠️ ALASAN KELAS INI ADA: penetralan formula.
 *
 * Rekap diekspor lalu dibuka panitia di komputernya. Nilai apa pun yang diawali
 * `=`, `+`, `-`, atau `@` akan dianggap Excel sebagai FORMULA dan dijalankan di
 * komputer itu. Satu nama mahasiswa yang diawali `=` sudah cukup. Karena itu
 * seluruh nilai teks ditulis sebagai TIPE STRING EKSPLISIT, bukan dibiarkan
 * ditebak PhpSpreadsheet.
 */
class ExcelExportService
{
    /** Karakter yang membuat Excel memperlakukan sel sebagai formula. */
    private const AWALAN_BERBAHAYA = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * @param  array<int, string>  $header
     * @param  iterable<int, array<int, mixed>>  $baris
     * @return string  isi berkas .xlsx sebagai string biner
     */
    public function buat(array $header, iterable $baris, string $judulSheet = 'Data'): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        // Nama sheet Excel maksimal 31 karakter dan tidak boleh memuat : \ / ? * [ ]
        $sheet->setTitle(mb_substr(preg_replace('/[:\\\\\/?*\[\]]/', '-', $judulSheet) ?? 'Data', 0, 31));

        foreach ($header as $kolom => $label) {
            $sheet->setCellValueExplicit([$kolom + 1, 1], $label, DataType::TYPE_STRING);
        }

        $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '008F4F']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $nomorBaris = 2;

        foreach ($baris as $isi) {
            foreach (array_values($isi) as $kolom => $nilai) {
                $sheet->setCellValueExplicit(
                    [$kolom + 1, $nomorBaris],
                    $this->netralkan($nilai),
                    DataType::TYPE_STRING,
                );
            }
            $nomorBaris++;
        }

        foreach (range(1, max(1, count($header))) as $kolom) {
            $sheet->getColumnDimensionByColumn($kolom)->setAutoSize(true);
        }

        $sheet->freezePane('A2');

        $temp = tempnam(sys_get_temp_dir(), 'xlsx');
        (new Xlsx($spreadsheet))->save($temp);

        $isi = (string) file_get_contents($temp);
        @unlink($temp);

        return $isi;
    }

    /**
     * Menetralkan nilai yang bisa dieksekusi Excel sebagai formula.
     *
     * Petik tunggal di depan membuat Excel menampilkannya apa adanya sebagai
     * teks. Ini melindungi komputer panitia, bukan server kita — dan justru
     * karena itu mudah terlupakan.
     */
    private function netralkan(mixed $nilai): string
    {
        if ($nilai === null || $nilai === '') {
            return '';
        }

        $teks = (string) $nilai;

        foreach (self::AWALAN_BERBAHAYA as $awalan) {
            if (str_starts_with($teks, $awalan)) {
                return "'".$teks;
            }
        }

        return $teks;
    }
}

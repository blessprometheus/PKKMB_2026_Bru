<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

/**
 * Impor data mahasiswa baru dari Excel/CSV bagian PMB.
 * Lihat docs/02-data-model.md §5 dan docs/04-security.md §5.
 *
 * Perilaku yang disengaja:
 * - Header dicocokkan berdasarkan NAMA, bukan urutan kolom.
 * - Header wajib tidak ditemukan → SELURUH impor dibatalkan, bukan sebagian.
 * - Baris yang gagal validasi dilaporkan dengan nomor barisnya; baris lain tetap masuk.
 * - Upsert berdasarkan NIM; `attendance_token` mahasiswa lama TIDAK diubah,
 *   supaya QR yang sudah tercetak tetap sah.
 */
class StudentImportService
{
    /** @var array<int, array{row:int, column:string, message:string}> */
    private array $errors = [];

    public function import(string $absolutePath, ImportBatch $batch): ImportBatch
    {
        $rows = $this->readRows($absolutePath);

        if ($rows === []) {
            throw new RuntimeException('Berkas kosong atau tidak memiliki baris data.');
        }

        $header = array_shift($rows);
        $map = $this->mapColumns($header);

        $maxRows = (int) config('pkkmb.import.max_rows');
        if (count($rows) > $maxRows) {
            throw new RuntimeException(
                "Berkas berisi ".count($rows)." baris, melebihi batas {$maxRows} baris. Periksa apakah berkasnya benar."
            );
        }

        $inserted = 0;
        $updated = 0;
        $seenNims = [];

        foreach ($rows as $index => $row) {
            // +2 = 1 baris header + indeks mulai dari 0, sehingga nomor yang
            // dilaporkan cocok dengan nomor baris di Excel.
            $excelRow = $index + 2;

            $data = $this->extractRow($row, $map);

            if ($this->isBlankRow($data)) {
                continue;
            }

            $validated = $this->validateRow($data, $excelRow, $seenNims);

            if ($validated === null) {
                continue;
            }

            $seenNims[$validated['nim']] = $excelRow;

            $existing = Student::where('nim', $validated['nim'])->first();

            if ($existing !== null) {
                // JANGAN sentuh attendance_token di sini.
                $existing->update($validated);
                $updated++;
            } else {
                Student::create($validated + ['import_batch_id' => $batch->id]);
                $inserted++;
            }
        }

        $batch->update([
            'total_rows' => count($rows),
            'inserted_count' => $inserted,
            'updated_count' => $updated,
            'failed_count' => count($this->errors),
            'error_report' => $this->errors === [] ? null : $this->errors,
            'status' => ImportBatch::STATUS_DONE,
        ]);

        return $batch->refresh();
    }

    /**
     * Membaca seluruh baris sebagai array string.
     *
     * `setReadDataOnly` penting: tanpa itu PhpSpreadsheet ikut memuat gaya,
     * gambar, dan rumus — boros memori untuk berkas yang hanya perlu dibaca isinya.
     *
     * @return array<int, array<int, mixed>>
     */
    private function readRows(string $absolutePath): array
    {
        $reader = IOFactory::createReaderForFile($absolutePath);
        $reader->setReadDataOnly(true);

        $sheet = $reader->load($absolutePath)->getActiveSheet();

        return $sheet->toArray(null, true, false, false);
    }

    /**
     * Mencocokkan header berkas dengan kolom database.
     *
     * @param  array<int, mixed>  $header
     * @return array<string, int>  nama kolom DB => indeks kolom di berkas
     */
    private function mapColumns(array $header): array
    {
        $aliases = config('pkkmb.import.column_aliases');
        $required = config('pkkmb.import.required_columns');

        $normalisedHeader = [];
        foreach ($header as $index => $label) {
            $normalisedHeader[$index] = $this->normaliseHeader((string) $label);
        }

        $map = [];
        foreach ($aliases as $column => $candidates) {
            foreach ($normalisedHeader as $index => $label) {
                if ($label !== '' && in_array($label, $candidates, true)) {
                    $map[$column] = $index;
                    break;
                }
            }
        }

        $missing = array_values(array_diff($required, array_keys($map)));

        if ($missing !== []) {
            // Pesan menyebut apa yang dicari DAN apa yang ditemukan — tanpa itu
            // admin harus menebak kenapa berkasnya ditolak.
            $found = implode(', ', array_filter($normalisedHeader)) ?: '(tidak ada header terbaca)';
            $wanted = [];
            foreach ($missing as $column) {
                $wanted[] = $column.' (misalnya: '.implode(' / ', $aliases[$column]).')';
            }

            throw new RuntimeException(
                'Impor dibatalkan. Kolom wajib tidak ditemukan: '.implode('; ', $wanted).
                '. Header yang terbaca di berkas: '.$found.'.'
            );
        }

        return $map;
    }

    private function normaliseHeader(string $label): string
    {
        $label = mb_strtolower(trim($label));
        $label = preg_replace('/[^\p{L}\p{N}]+/u', '_', $label) ?? '';

        return trim($label, '_');
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $map
     * @return array<string, string|null>
     */
    private function extractRow(array $row, array $map): array
    {
        $data = [];

        foreach ($map as $column => $index) {
            $value = $row[$index] ?? null;

            if ($column === 'birth_date') {
                $data[$column] = $this->normaliseDate($value);

                continue;
            }

            $data[$column] = $this->cleanValue($value);
        }

        if (isset($data['gender'])) {
            $data['gender'] = $this->normaliseGender($data['gender']);
        }

        return $data;
    }

    private function cleanValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Buang karakter kontrol, rapikan spasi ganda (docs/04-security.md §5.2).
        $value = (string) $value;
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Excel menyimpan tanggal sebagai angka seri, bukan teks. Kalau tidak
     * dikonversi, "1 Januari 2007" masuk database sebagai "39083".
     */
    private function normaliseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        $text = trim((string) $value);

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd/m/y'] as $format) {
            $parsed = \DateTime::createFromFormat($format, $text);

            if ($parsed !== false) {
                return $parsed->format('Y-m-d');
            }
        }

        return null;
    }

    private function normaliseGender(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match (mb_strtoupper($value)) {
            'L', 'LAKI-LAKI', 'LAKI LAKI', 'PRIA', 'M' => 'L',
            'P', 'PEREMPUAN', 'WANITA', 'F' => 'P',
            default => null,
        };
    }

    /** @param  array<string, string|null>  $data */
    private function isBlankRow(array $data): bool
    {
        return array_filter($data, fn ($v) => $v !== null && $v !== '') === [];
    }

    /**
     * @param  array<string, string|null>  $data
     * @param  array<string, int>  $seenNims
     * @return array<string, mixed>|null  null bila baris ditolak
     */
    private function validateRow(array $data, int $excelRow, array $seenNims): ?array
    {
        $validator = Validator::make($data, [
            'nim' => ['required', 'string', 'max:30', 'regex:/^[0-9A-Za-z.\-]+$/'],
            'name' => ['required', 'string', 'max:160'],
            'faculty' => ['required', 'string', 'max:120'],
            'study_program' => ['required', 'string', 'max:160'],
            'group_name' => ['nullable', 'string', 'max:80'],
            'gender' => ['nullable', 'in:L,P'],
            'birth_date' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:25'],
            'email' => ['nullable', 'email:rfc', 'max:160'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $column => $messages) {
                $this->errors[] = [
                    'row' => $excelRow,
                    'column' => $column,
                    'message' => $messages[0],
                ];
            }

            return null;
        }

        $validated = $validator->validated();

        // Duplikat DI DALAM berkas yang sama. Duplikat terhadap data yang sudah
        // ada di database bukan error — itu pembaruan (upsert).
        if (isset($seenNims[$validated['nim']])) {
            $this->errors[] = [
                'row' => $excelRow,
                'column' => 'nim',
                'message' => 'NIM sudah dipakai di baris '.$seenNims[$validated['nim']].' pada berkas yang sama.',
            ];

            return null;
        }

        return $validated;
    }
}

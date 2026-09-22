<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi tambah/ubah mahasiswa. Lihat docs/04-security.md §6.
 *
 * Otorisasi ditangani middleware `role:admin` di routes/api.php.
 */
class StudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $studentId = $this->route('student')?->id;

        return [
            // NIM diperlakukan sebagai TEKS, bukan angka — Excel membuang nol di
            // depan, dan sebagian NIM memuat titik/strip (docs/04-security.md §6).
            'nim' => [
                'required', 'string', 'max:30',
                'regex:/^[0-9A-Za-z.\-]+$/',
                Rule::unique('students', 'nim')->ignore($studentId),
            ],
            'name' => ['required', 'string', 'max:160'],
            'faculty' => ['required', 'string', 'max:120'],
            'study_program' => ['required', 'string', 'max:160'],
            'group_name' => ['nullable', 'string', 'max:80'],
            'gender' => ['nullable', Rule::in(['L', 'P'])],
            'birth_date' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:25'],
            'email' => ['nullable', 'email:rfc', 'max:160'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nim.regex' => 'NIM hanya boleh berisi huruf, angka, titik, dan strip.',
            'nim.unique' => 'NIM ini sudah terdaftar atas nama mahasiswa lain.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Rapikan sebelum divalidasi: spasi di ujung adalah penyebab paling sering
        // "NIM tidak ditemukan" padahal datanya ada.
        $this->merge(array_filter([
            'nim' => is_string($this->nim) ? trim($this->nim) : $this->nim,
            'name' => is_string($this->name) ? preg_replace('/\s+/u', ' ', trim($this->name)) : $this->name,
        ], fn ($v) => $v !== null));
    }
}

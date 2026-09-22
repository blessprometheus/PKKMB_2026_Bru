<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Peta kolom impor Excel/CSV dari bagian PMB
    |--------------------------------------------------------------------------
    |
    | ⚠️ INI RANCANGAN, BUKAN HASIL VERIFIKASI.
    |
    | Nama header di bawah diturunkan dari kebutuhan nametag dan rekap, BUKAN
    | dari file Excel PMB yang sebenarnya (D1 di docs/01-prd.md §8 belum diterima).
    |
    | Begitu file asli datang: buka filenya, cocokkan header sebenarnya, perbarui
    | daftar ini, LALU perbarui docs/02-data-model.md §5.1 agar dokumen tidak usang.
    | Lihat AGENTS.md §4.
    |
    | Pencocokan memakai NAMA header, bukan urutan kolom — urutan kolom di file
    | PMB bisa berubah kapan saja tanpa pemberitahuan.
    |
    */
    'import' => [

        // Header dinormalkan dulu (huruf kecil, tanda baca/spasi jadi garis bawah)
        // sebelum dicocokkan dengan alias di bawah.
        'column_aliases' => [
            'nim' => ['nim', 'no_induk', 'nomor_induk', 'nomor_induk_mahasiswa', 'npm'],
            'name' => ['nama', 'nama_lengkap', 'nama_mahasiswa', 'nama_maba'],
            'faculty' => ['fakultas'],
            'study_program' => ['prodi', 'program_studi', 'jurusan'],
            'group_name' => ['kelompok', 'gugus', 'kelompok_pkkmb', 'nama_kelompok'],
            'gender' => ['jk', 'jenis_kelamin', 'l_p', 'gender'],
            'birth_date' => ['tanggal_lahir', 'tgl_lahir'],
            'phone' => ['no_hp', 'nomor_hp', 'telepon', 'no_telepon', 'hp', 'whatsapp'],
            'email' => ['email', 'surel', 'alamat_email'],
        ],

        // Tanpa salah satu dari ini, impor DIBATALKAN SELURUHNYA — bukan
        // diimpor sebagian. Data mahasiswa yang masuk setengah-setengah lebih
        // berbahaya daripada tidak masuk sama sekali.
        'required_columns' => ['nim', 'name', 'faculty', 'study_program'],

        'max_file_size_kb' => 10240,  // 10 MB, sejalan dengan docs/04-security.md §5.1

        // Batas wajar untuk ±550 peserta. Melebihi ini hampir pasti file yang salah.
        'max_rows' => 5000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Endpoint lookup publik
    |--------------------------------------------------------------------------
    |
    | Saklar cadangan: kalau saat acara terdeteksi ada yang menyapu NIM, ini bisa
    | dinyalakan lewat .env tanpa deploy ulang (docs/04-security.md §2).
    |
    */
    'lookup' => [
        'require_birthdate' => env('LOOKUP_REQUIRE_BIRTHDATE', false),
        'per_minute' => env('LOOKUP_RATE_PER_MINUTE', 10),
        'per_hour' => env('LOOKUP_RATE_PER_HOUR', 40),
    ],

];

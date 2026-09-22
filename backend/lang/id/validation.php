<?php

/**
 * Pesan validasi Bahasa Indonesia.
 *
 * Seluruh pesan yang tampil ke pengguna wajib Bahasa Indonesia (AGENTS.md §1).
 * Daftar ini sengaja tidak selengkap bawaan Laravel — hanya aturan yang benar-benar
 * dipakai proyek ini. Kalau memakai aturan baru, tambahkan terjemahannya di sini.
 */
return [
    'accepted' => ':attribute wajib disetujui.',
    'after' => ':attribute harus tanggal setelah :date.',
    'after_or_equal' => ':attribute harus tanggal setelah atau sama dengan :date.',
    'alpha_dash' => ':attribute hanya boleh berisi huruf, angka, strip, dan garis bawah.',
    'alpha_num' => ':attribute hanya boleh berisi huruf dan angka.',
    'array' => ':attribute harus berupa daftar.',
    'before' => ':attribute harus tanggal sebelum :date.',
    'before_or_equal' => ':attribute harus tanggal sebelum atau sama dengan :date.',
    'boolean' => ':attribute hanya boleh bernilai benar atau salah.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'current_password' => 'Kata sandi salah.',
    'date' => ':attribute bukan tanggal yang valid.',
    'date_format' => ':attribute tidak sesuai format :format.',
    'different' => ':attribute dan :other harus berbeda.',
    'digits' => ':attribute harus terdiri dari :digits angka.',
    'digits_between' => ':attribute harus terdiri dari :min sampai :max angka.',
    'email' => 'Format :attribute tidak valid.',
    'exists' => ':attribute yang dipilih tidak ditemukan.',
    'file' => ':attribute harus berupa berkas.',
    'filled' => ':attribute wajib diisi.',
    'image' => ':attribute harus berupa gambar.',
    'in' => ':attribute yang dipilih tidak valid.',
    'integer' => ':attribute harus berupa angka bulat.',
    'max' => [
        'array' => ':attribute tidak boleh lebih dari :max item.',
        'file' => ':attribute tidak boleh lebih besar dari :max kilobyte.',
        'numeric' => ':attribute tidak boleh lebih dari :max.',
        'string' => ':attribute tidak boleh lebih dari :max karakter.',
    ],
    'mimes' => ':attribute harus berupa berkas bertipe: :values.',
    'min' => [
        'array' => ':attribute harus berisi minimal :min item.',
        'file' => ':attribute harus minimal :min kilobyte.',
        'numeric' => ':attribute harus minimal :min.',
        'string' => ':attribute harus minimal :min karakter.',
    ],
    'not_in' => ':attribute yang dipilih tidak valid.',
    'numeric' => ':attribute harus berupa angka.',
    'prohibited' => ':attribute tidak boleh diisi.',
    'regex' => 'Format :attribute tidak valid.',
    'required' => ':attribute wajib diisi.',
    'required_if' => ':attribute wajib diisi bila :other bernilai :value.',
    'required_with' => ':attribute wajib diisi bila ada :values.',
    'same' => ':attribute dan :other harus sama.',
    'size' => [
        'array' => ':attribute harus berisi :size item.',
        'file' => ':attribute harus berukuran :size kilobyte.',
        'numeric' => ':attribute harus bernilai :size.',
        'string' => ':attribute harus :size karakter.',
    ],
    'string' => ':attribute harus berupa teks.',
    'unique' => ':attribute sudah digunakan.',
    'uploaded' => ':attribute gagal diunggah. Periksa ukuran berkas.',
    'url' => 'Format :attribute tidak valid.',

    'password' => [
        'letters' => ':attribute harus memuat minimal satu huruf.',
        'mixed' => ':attribute harus memuat huruf besar dan huruf kecil.',
        'numbers' => ':attribute harus memuat minimal satu angka.',
        'symbols' => ':attribute harus memuat minimal satu simbol.',
        'uncompromised' => ':attribute pernah bocor di kebocoran data. Pilih kata sandi lain.',
    ],

    'custom' => [],

    /**
     * Nama field dalam Bahasa Indonesia, supaya pesan terbaca wajar
     * ("NIM wajib diisi", bukan "nim wajib diisi").
     */
    'attributes' => [
        'nim' => 'NIM',
        'name' => 'Nama',
        'email' => 'Email',
        'password' => 'Kata sandi',
        'role' => 'Peran',
        'is_active' => 'Status aktif',
        'faculty' => 'Fakultas',
        'study_program' => 'Program studi',
        'group_name' => 'Kelompok',
        'gender' => 'Jenis kelamin',
        'birth_date' => 'Tanggal lahir',
        'phone' => 'Nomor HP',
        'file' => 'Berkas',
        'event_date' => 'Tanggal kegiatan',
        'starts_at' => 'Waktu mulai',
        'ends_at' => 'Waktu selesai',
        'attendance_session_id' => 'Sesi presensi',
        'student_id' => 'Mahasiswa',
        'token' => 'Token',
        'device_label' => 'Label perangkat',
        'per_page' => 'Jumlah per halaman',
    ],
];

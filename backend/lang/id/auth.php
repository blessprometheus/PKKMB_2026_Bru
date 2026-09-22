<?php

/**
 * Pesan autentikasi.
 *
 * `failed` sengaja tidak membedakan "email tidak terdaftar" dari "kata sandi salah" —
 * pembedaannya hanya berguna bagi orang yang sedang menebak akun.
 * Lihat docs/04-security.md §1.
 */
return [
    'failed' => 'Email atau kata sandi salah.',
    'password' => 'Kata sandi salah.',
    'throttle' => 'Terlalu banyak percobaan masuk. Coba lagi dalam :seconds detik.',
];

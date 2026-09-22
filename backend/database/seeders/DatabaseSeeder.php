<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeder sengaja dibiarkan kosong.
     *
     * Akun panitia TIDAK dibuat lewat seeder, karena seeder berisi kata sandi
     * akan ikut ter-commit ke repositori. Gunakan:
     *
     *     php artisan pkkmb:create-admin
     *
     * Lihat docs/04-security.md §1 dan docs/06-deployment.md §5 langkah 6.
     *
     * Data mahasiswa masuk lewat impor Excel dari bagian PMB, bukan seeder.
     */
    public function run(): void
    {
        //
    }
}

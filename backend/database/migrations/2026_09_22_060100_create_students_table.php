<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mahasiswa baru peserta PKKMB. Lihat docs/02-data-model.md §2.2.
 *
 * PERINGATAN: kolom di sini adalah RANCANGAN, diturunkan dari kebutuhan nametag
 * dan rekap — BUKAN dari file Excel PMB yang sebenarnya (D1 belum diterima).
 * Sebelum importer ditulis, cocokkan dengan file asli lalu perbarui dokumen.
 * Lihat AGENTS.md §4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();

            // NIM disimpan sebagai string, bukan angka — Excel membuang nol di depan.
            $table->string('nim', 30)->unique();
            $table->string('name', 160);
            $table->string('faculty', 120);
            $table->string('study_program', 160);
            $table->string('group_name', 80)->nullable();   // gugus/kelompok — TODO: konfirmasi D6
            $table->string('gender', 1)->nullable();

            // Tiga kolom di bawah TIDAK BOLEH dikembalikan endpoint lookup publik.
            // Lihat docs/04-security.md §2 butir 2.
            $table->date('birth_date')->nullable();
            $table->string('phone', 25)->nullable();
            $table->string('email', 160)->nullable();

            $table->string('photo_path', 255)->nullable();

            // Isi QR presensi: 32 karakter acak kriptografis, BUKAN turunan NIM.
            // Alasannya di docs/02-data-model.md §4.
            $table->string('attendance_token', 32)->unique();

            $table->foreignId('import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->timestampsTz();

            $table->index('faculty');
            $table->index('group_name');
        });

        DB::statement("ALTER TABLE students ADD CONSTRAINT students_gender_check CHECK (gender IS NULL OR gender IN ('L','P'))");

        // Pencarian nama oleh admin. PostgreSQL: full text search, bukan LIKE '%...%'.
        DB::statement("CREATE INDEX students_name_search ON students USING gin (to_tsvector('simple', name))");
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};

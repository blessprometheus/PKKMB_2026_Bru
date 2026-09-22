<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Akun panitia. Menggantikan tabel `users` bawaan Laravel.
 * Lihat docs/02-data-model.md §2.1.
 *
 * Hanya dua peran: `admin` (penuh) dan `operator` (hanya halaman scan).
 * Pembatasan operator dijelaskan di docs/04-security.md §1 — laptop petugas
 * dipakai terbuka di aula, jadi tidak boleh bisa membuka daftar lengkap mahasiswa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 160)->unique();
            $table->string('password');
            $table->string('role', 20)->default('operator');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE admins ADD CONSTRAINT admins_role_check CHECK (role IN ('admin','operator'))");

        // Tabel sesi HTTP Laravel (SESSION_DRIVER=database).
        // Namanya `sessions` — JANGAN tertukar dengan `attendance_sessions`.
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('admins');
    }
};

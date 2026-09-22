<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pantauan penyapuan NIM di endpoint lookup publik. Lihat docs/02-data-model.md §2.8.
 *
 * Rate limit sendiri ditangani Laravel RateLimiter; tabel ini untuk MELIHAT
 * serangannya. Kalau satu IP mencari > 50 NIM berbeda dalam sejam, itu bukan
 * mahasiswa yang lupa NIM-nya (docs/04-security.md §2 butir 5).
 *
 * NIM disimpan sebagai HASH — tabel pemantauan tidak boleh menjadi salinan
 * daftar NIM yang bocor lewat pintu lain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lookup_attempts', function (Blueprint $table) {
            $table->id();
            $table->ipAddress('ip');
            $table->string('nim_hash', 64);
            $table->boolean('found');
            $table->timestampTz('created_at')->nullable();

            $table->index(['ip', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lookup_attempts');
    }
};

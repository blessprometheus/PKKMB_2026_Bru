<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sesi presensi PKKMB. Lihat docs/02-data-model.md §2.3.
 *
 * Nama tabel sengaja `attendance_sessions`, BUKAN `sessions` — `sessions`
 * sudah dipakai Laravel untuk sesi HTTP.
 *
 * TODO: jumlah hari & sesi menunggu D2 (docs/01-prd.md §8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->date('event_date');

            // Scan di luar jendela waktu ini DITOLAK dengan pesan jelas, bukan
            // diterima diam-diam — supaya tidak ada kehadiran di sesi yang salah.
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');

            $table->boolean('is_active')->default(false);
            $table->timestampsTz();

            $table->index('event_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};

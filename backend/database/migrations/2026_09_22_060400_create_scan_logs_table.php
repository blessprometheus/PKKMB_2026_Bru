<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak setiap pemindaian, termasuk yang DITOLAK. Lihat docs/02-data-model.md §2.6.
 *
 * Inilah yang memungkinkan menjawab "kenapa si A merasa sudah absen tapi tidak
 * tercatat" setelah acara bubar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('attendance_session_id')->nullable()->constrained('attendance_sessions')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('result', 24);

            // HASH dari input mentah, bukan tokennya. Kalau token disimpan polos,
            // tabel log ini menjadi daftar QR yang bisa dipakai ulang.
            // Lihat docs/04-security.md §9.
            $table->string('raw_input_hash', 64)->nullable();

            $table->ipAddress('ip')->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->index(['attendance_session_id', 'created_at']);
        });

        DB::statement("ALTER TABLE scan_logs ADD CONSTRAINT scan_logs_result_check CHECK (result IN ('recorded','duplicate','unknown_token','session_closed','rate_limited'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_logs');
    }
};

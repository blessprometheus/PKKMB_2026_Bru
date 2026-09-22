<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan kehadiran. Lihat docs/02-data-model.md §2.4.
 *
 * Batasan UNIQUE (student_id, attendance_session_id) adalah YANG MENJADIKAN
 * duplikat mustahil — bukan pengecekan if() di PHP. Tiga scanner memindai
 * bersamaan; pengecekan di aplikasi bisa kalah balapan, batasan database tidak.
 * Pencatatan memakai INSERT ... ON CONFLICT DO NOTHING (docs/03-api-spec.md §5.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('attendance_session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestampTz('scanned_at');
            $table->string('method', 10)->default('qr');
            $table->string('device_label', 60)->nullable();   // mis. "Pintu A"
            $table->timestampsTz();

            $table->unique(['student_id', 'attendance_session_id'], 'attendances_unique_per_session');
            $table->index(['attendance_session_id', 'scanned_at']);
        });

        DB::statement("ALTER TABLE attendances ADD CONSTRAINT attendances_method_check CHECK (method IN ('qr','manual'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};

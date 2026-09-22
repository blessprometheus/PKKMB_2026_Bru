<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak aksi admin yang mengubah/menghapus data. Lihat docs/02-data-model.md §2.7.
 *
 * `meta` TIDAK BOLEH memuat data pribadi mentah — simpan id, bukan nama & NIM
 * (docs/04-security.md §9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('action', 60);            // mis. student.updated, attendance.deleted
            $table->string('target_type', 60)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->jsonb('meta')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->index(['action', 'created_at']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

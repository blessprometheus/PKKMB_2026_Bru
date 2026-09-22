<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat impor Excel/CSV dari bagian PMB. Lihat docs/02-data-model.md §2.5.
 *
 * Dibuat sebelum `students` karena students merujuk ke tabel ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('original_filename', 255);
            // Disimpan di storage/app/imports — DI LUAR webroot (docs/04-security.md §5.1)
            $table->string('stored_path', 255);
            $table->integer('total_rows')->default(0);
            $table->integer('inserted_count')->default(0);
            $table->integer('updated_count')->default(0);
            $table->integer('failed_count')->default(0);
            // Daftar {row, column, message} agar admin bisa memperbaiki barisnya
            $table->jsonb('error_report')->nullable();
            $table->string('status', 20)->default('processing');
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE import_batches ADD CONSTRAINT import_batches_status_check CHECK (status IN ('processing','done','failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Mahasiswa baru peserta PKKMB. Lihat docs/02-data-model.md §2.2.
 */
#[Fillable([
    'nim', 'name', 'faculty', 'study_program', 'group_name',
    'gender', 'birth_date', 'phone', 'email', 'photo_path', 'import_batch_id',
])]
#[Hidden(['attendance_token'])]
class Student extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // Token dibuat saat mahasiswa pertama kali masuk database, sekali saja.
        // Impor ulang TIDAK boleh mengubahnya — QR yang sudah dicetak harus tetap sah
        // (docs/02-data-model.md §5 butir 3).
        static::creating(function (Student $student) {
            $student->attendance_token ??= self::generateToken();
        });
    }

    /**
     * 32 karakter acak kriptografis. BUKAN turunan NIM.
     *
     * NIM berurutan dan bisa ditebak; kalau QR berisi NIM, siapa pun bisa
     * mencetak QR seluruh angkatan tanpa menyentuh sistem ini.
     * Lihat docs/02-data-model.md §4.
     */
    public static function generateToken(): string
    {
        do {
            $token = bin2hex(random_bytes(16));
        } while (self::where('attendance_token', $token)->exists());

        return $token;
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Pencarian nama/NIM untuk dashboard admin.
     *
     * PostgreSQL: `ILIKE`, bukan `LIKE` — `LIKE` di Postgres sensitif huruf
     * besar/kecil, jadi mencari "budi" tidak akan menemukan "Budi".
     * Lihat docs/02-data-model.md §1.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('nim', 'ILIKE', '%'.$term.'%')
                ->orWhere('name', 'ILIKE', '%'.$term.'%');
        });
    }

    /**
     * Data yang boleh dilihat publik lewat endpoint lookup.
     *
     * Sengaja dibuat sebagai daftar-putih, bukan menghapus beberapa field dari
     * seluruh atribut. Kalau nanti ada kolom baru berisi data pribadi, kolom itu
     * TIDAK otomatis ikut bocor. Lihat docs/04-security.md §2 butir 2.
     *
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'nim' => $this->nim,
            'name' => $this->name,
            'faculty' => $this->faculty,
            'study_program' => $this->study_program,
            'group_name' => $this->group_name,
        ];
    }

    /**
     * Data untuk layar petugas saat memindai. Cukup untuk mencocokkan orang,
     * tanpa kontak atau tanggal lahir — layar ini menghadap antrean.
     *
     * @return array<string, mixed>
     */
    public function toScanArray(): array
    {
        return [
            'name' => $this->name,
            'nim' => $this->nim,
            'study_program' => $this->study_program,
            'group_name' => $this->group_name,
        ];
    }
}

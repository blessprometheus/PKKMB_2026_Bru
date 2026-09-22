<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Pantauan penyapuan NIM di endpoint lookup publik.
 * Lihat docs/02-data-model.md §2.8 dan docs/04-security.md §2 butir 5.
 *
 * NIM disimpan sebagai HASH. Tabel pemantauan tidak boleh berubah menjadi
 * salinan daftar NIM yang justru bocor lewat pintu lain.
 */
#[Fillable(['ip', 'nim_hash', 'found'])]
class LookupAttempt extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['found' => 'boolean'];
    }

    public static function hashNim(string $nim): string
    {
        return hash('sha256', mb_strtolower(trim($nim)));
    }
}

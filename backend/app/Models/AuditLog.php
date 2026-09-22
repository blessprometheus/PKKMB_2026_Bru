<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak aksi admin. Lihat docs/02-data-model.md §2.7.
 *
 * `meta` TIDAK BOLEH memuat data pribadi mentah — simpan id, bukan nama & NIM
 * (docs/04-security.md §9).
 */
#[Fillable(['admin_id', 'action', 'target_type', 'target_id', 'meta', 'ip', 'user_agent'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}

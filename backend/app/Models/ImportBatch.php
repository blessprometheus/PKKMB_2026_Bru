<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Riwayat impor Excel/CSV dari PMB. Lihat docs/02-data-model.md §2.5. */
#[Fillable([
    'admin_id', 'original_filename', 'stored_path', 'total_rows',
    'inserted_count', 'updated_count', 'failed_count', 'error_report', 'status',
])]
class ImportBatch extends Model
{
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return ['error_report' => 'array'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}

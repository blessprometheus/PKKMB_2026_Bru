<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak setiap pemindaian, termasuk yang ditolak.
 * Lihat docs/02-data-model.md §2.6.
 *
 * Inilah yang memungkinkan menjawab "kenapa si A merasa sudah absen tapi tidak
 * tercatat" setelah acara bubar.
 */
#[Fillable([
    'admin_id', 'attendance_session_id', 'student_id',
    'result', 'raw_input_hash', 'ip',
])]
class ScanLog extends Model
{
    public const UPDATED_AT = null;

    public const RESULT_RECORDED = 'recorded';
    public const RESULT_DUPLICATE = 'duplicate';
    public const RESULT_UNKNOWN_TOKEN = 'unknown_token';
    public const RESULT_SESSION_CLOSED = 'session_closed';
    public const RESULT_RATE_LIMITED = 'rate_limited';

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}

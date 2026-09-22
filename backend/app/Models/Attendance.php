<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Catatan kehadiran. Lihat docs/02-data-model.md §2.4. */
#[Fillable(['student_id', 'attendance_session_id', 'admin_id', 'scanned_at', 'method', 'device_label'])]
class Attendance extends Model
{
    public const METHOD_QR = 'qr';
    public const METHOD_MANUAL = 'manual';

    protected function casts(): array
    {
        return ['scanned_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}

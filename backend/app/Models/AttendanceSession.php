<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sesi presensi. Lihat docs/02-data-model.md §2.3.
 * Namanya BUKAN `Session` — `sessions` sudah dipakai Laravel untuk sesi HTTP.
 */
#[Fillable(['name', 'event_date', 'starts_at', 'ends_at', 'is_active'])]
class AttendanceSession extends Model
{
    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** Scan di luar jendela waktu ditolak, bukan diterima diam-diam. */
    public function isOpenAt(\DateTimeInterface $moment): bool
    {
        return $this->is_active
            && $moment >= $this->starts_at
            && $moment <= $this->ends_at;
    }
}

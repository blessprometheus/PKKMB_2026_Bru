<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Pencatat jejak aksi admin. Lihat docs/02-data-model.md §2.7.
 *
 * ATURAN: `meta` hanya boleh berisi id dan keterangan teknis — JANGAN masukkan
 * nama, NIM, nomor HP, atau email mahasiswa. Log yang penuh data pribadi menjadi
 * salinan kedua dari database yang perlindungannya lebih lemah
 * (docs/04-security.md §9).
 */
class AuditLogger
{
    /** @param  array<string, mixed>  $meta */
    public static function record(
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        array $meta = [],
        ?Request $request = null,
    ): void {
        $request ??= request();

        AuditLog::create([
            'admin_id' => Auth::id(),
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'meta' => $meta === [] ? null : $meta,
            'ip' => $request?->ip(),
            'user_agent' => mb_substr((string) $request?->userAgent(), 0, 255),
        ]);
    }
}

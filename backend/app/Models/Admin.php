<?php

namespace App\Models;

use Database\Factories\AdminFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Akun panitia: admin (penuh) atau operator (hanya halaman scan).
 * Lihat docs/02-data-model.md §2.1 dan docs/04-security.md §1.
 */
#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class Admin extends Authenticatable
{
    /** @use HasFactory<AdminFactory> */
    use HasFactory;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_OPERATOR = 'operator';

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Akses penuh ke dashboard. Operator sengaja tidak punya ini — laptop
     * petugas dipakai terbuka di aula, tidak boleh bisa membuka daftar
     * lengkap mahasiswa beserta kontaknya.
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /** Boleh membuka halaman scan: admin maupun operator. */
    public function canScan(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_OPERATOR], true);
    }
}

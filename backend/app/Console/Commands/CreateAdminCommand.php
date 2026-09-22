<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password as promptPassword;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Membuat akun panitia secara interaktif di server.
 *
 * Sengaja TIDAK memakai seeder: seeder berisi kata sandi akan ikut ter-commit
 * ke repositori. Lihat docs/04-security.md §1 dan docs/06-deployment.md §5 langkah 6.
 */
class CreateAdminCommand extends Command
{
    protected $signature = 'pkkmb:create-admin';

    protected $description = 'Membuat akun panitia (admin atau operator) secara interaktif';

    public function handle(): int
    {
        $this->info('Membuat akun panitia PKKMB UNINUS 2026.');

        $name = text(
            label: 'Nama lengkap',
            required: 'Nama wajib diisi.',
        );

        $email = text(
            label: 'Email (dipakai untuk login)',
            required: 'Email wajib diisi.',
            validate: function (string $value) {
                $validator = Validator::make(
                    ['email' => $value],
                    ['email' => ['email:rfc', 'max:160', 'unique:admins,email']],
                    ['email.unique' => 'Email ini sudah terdaftar.', 'email.email' => 'Format email tidak valid.']
                );

                return $validator->fails() ? $validator->errors()->first('email') : null;
            },
        );

        $role = select(
            label: 'Peran',
            options: [
                Admin::ROLE_ADMIN => 'admin — akses penuh dashboard',
                Admin::ROLE_OPERATOR => 'operator — hanya halaman scan presensi',
            ],
            default: Admin::ROLE_OPERATOR,
        );

        // Panjang minimum berbeda per peran, sesuai docs/04-security.md §1.
        $minLength = $role === Admin::ROLE_ADMIN ? 12 : 10;

        $plain = promptPassword(
            label: "Kata sandi (minimal {$minLength} karakter)",
            required: 'Kata sandi wajib diisi.',
            validate: function (string $value) use ($minLength) {
                $validator = Validator::make(
                    ['password' => $value],
                    ['password' => [Password::min($minLength)->letters()->numbers()]],
                    ['password' => "Kata sandi minimal {$minLength} karakter dan memuat huruf serta angka."]
                );

                return $validator->fails() ? $validator->errors()->first('password') : null;
            },
        );

        $confirm = promptPassword(label: 'Ulangi kata sandi');

        if ($plain !== $confirm) {
            $this->error('Kata sandi tidak cocok. Akun tidak dibuat.');

            return self::FAILURE;
        }

        $admin = Admin::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($plain),
            'role' => $role,
            'is_active' => true,
        ]);

        $this->newLine();
        $this->info("Akun dibuat: {$admin->name} <{$admin->email}> (peran: {$admin->role})");
        $this->line('Kata sandi tidak ditampilkan dan tidak dicatat di mana pun.');

        return self::SUCCESS;
    }
}

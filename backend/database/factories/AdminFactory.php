<?php

namespace Database\Factories;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Admin>
 *
 * Hanya untuk test otomatis. JANGAN dipakai menyiapkan akun produksi —
 * akun nyata dibuat lewat `php artisan pkkmb:create-admin` di server,
 * supaya tidak ada kata sandi yang pernah masuk repositori
 * (docs/04-security.md §1).
 */
class AdminFactory extends Factory
{
    protected $model = Admin::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password-uji-hanya-untuk-test'),
            'role' => Admin::ROLE_OPERATOR,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Admin::ROLE_ADMIN,
        ]);
    }

    public function nonaktif(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}

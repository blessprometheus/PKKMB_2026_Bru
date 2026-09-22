<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 *
 * Hanya untuk test otomatis. Data mahasiswa nyata masuk lewat impor Excel PMB,
 * tidak pernah lewat factory atau seeder (docs/04-security.md §10).
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'nim' => (string) fake()->unique()->numberBetween(20260010000, 20260019999),
            'name' => fake()->name(),
            'faculty' => fake()->randomElement([
                'Fakultas Teknik',
                'Fakultas Keguruan dan Ilmu Pendidikan',
                'Fakultas Hukum',
                'Fakultas Ekonomi dan Bisnis',
            ]),
            'study_program' => fake()->randomElement([
                'Teknik Informatika',
                'Pendidikan Bahasa Indonesia',
                'Ilmu Hukum',
                'Manajemen',
            ]),
            'group_name' => 'Gugus '.fake()->numberBetween(1, 12),
            'gender' => fake()->randomElement(['L', 'P']),
        ];
    }
}

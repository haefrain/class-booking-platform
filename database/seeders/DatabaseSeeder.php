<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Demo personas — one per role, password "password".
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Amalia Admin',
            'email' => 'admin@classbooking.test',
        ]);

        User::factory()->instructor()->create([
            'name' => 'Iván Instructor',
            'email' => 'instructor@classbooking.test',
        ]);

        User::factory()->student()->create([
            'name' => 'Sofía Student',
            'email' => 'student@classbooking.test',
        ]);

        $this->call(DemoSeeder::class);
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            StudyProgramSeeder::class, // wajib: dipakai dropdown program studi
            UserSeeder::class,         // akun contoh untuk pengembangan
            DemoEventSeeder::class,    // opsional: tiga event contoh (ER-71)
        ]);
    }
}

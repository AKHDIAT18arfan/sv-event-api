<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Akun contoh untuk pengembangan. Kata sandi semuanya "password".
 * HAPUS atau ganti sebelum deployment ke VPS.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        // BEM SV
        User::updateOrCreate(['email' => 'admin@svevent.test'], [
            'name' => 'Admin BEM SV',
            'password' => 'password',
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);

        // Akun penyelenggara (dibuat SuperAdmin pada aplikasi sungguhan)
        User::updateOrCreate(['email' => 'panitia.d3ti@svevent.test'], [
            'name' => 'Panitia D3 Teknik Informatika',
            'password' => 'password',
            'role' => User::ROLE_PANITIA,
            'is_active' => true,
        ]);

        User::updateOrCreate(['email' => 'panitia.himadepsi@svevent.test'], [
            'name' => 'Panitia HIMADEPSI',
            'password' => 'password',
            'role' => User::ROLE_PANITIA,
            'is_active' => true,
        ]);

        // Peserta (jenis Umum/Mahasiswa dipilih saat mendaftar event)
        User::updateOrCreate(['email' => 'budi@svevent.test'], [
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'password' => 'password',
            'role' => User::ROLE_PESERTA,
            'is_active' => true,
        ]);

        User::updateOrCreate(['email' => 'siti@svevent.test'], [
            'name' => 'Siti Rahma',
            'phone' => '081298765432',
            'password' => 'password',
            'role' => User::ROLE_PESERTA,
            'is_active' => true,
        ]);
    }
}

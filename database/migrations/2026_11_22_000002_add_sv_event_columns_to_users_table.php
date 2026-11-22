<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Akun hanya menyimpan identitas dasar. Jenis peserta (Umum/Mahasiswa), NIM, dan
     * program studi dipilih saat mendaftar event dan disimpan di tabel registrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // super_admin (BEM SV) | panitia (akun penyelenggara) | peserta
            $table->string('role', 20)->default('peserta');
            $table->string('phone', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes(); // "hapus akun" = nonaktif, riwayat tiket tetap ada
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['role', 'phone', 'is_active']);
        });
    }
};

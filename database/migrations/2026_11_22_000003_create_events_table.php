<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('venue', 200);
            $table->string('poster_path')->nullable();
            // MAHASISWA = khusus mahasiswa, SEMUA = terbuka untuk mahasiswa dan umum
            $table->string('target_peserta', 12);
            // teks bebas, bisa kolaborasi (contoh: "D3TI x HIMADEPSI")
            $table->string('organizer_name', 200);
            $table->dateTime('registration_open_at');
            $table->dateTime('registration_close_at');
            // dibuka | ditutup  (tidak ada Draft; Selesai = Ditutup)
            $table->string('status', 12)->default('dibuka');
            // penghitung ID tiket per event: naik terus, tidak pernah dipakai ulang
            $table->unsignedInteger('last_ticket_no')->default(0);
            // akun panitia pemilik event
            $table->foreignId('created_by')->constrained('users');
            // penghapusan event = disembunyikan (soft delete), SuperAdmin wajib mengisi alasan
            $table->text('deleted_reason')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};

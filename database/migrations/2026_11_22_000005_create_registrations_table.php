<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            // event_id sengaja didenormalisasi agar constraint unik di bawah bisa dibuat
            $table->foreignId('event_id')->constrained('events')->restrictOnDelete();
            $table->foreignId('session_id')->constrained('event_sessions')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

            // Dipilih saat mendaftar event (bukan di akun)
            $table->string('participant_type', 12);                 // MAHASISWA | UMUM
            $table->string('nim', 30)->nullable();                  // hanya MAHASISWA
            $table->foreignId('study_program_id')->nullable()       // hanya MAHASISWA
                ->constrained('study_programs')->nullOnDelete();

            // ID tiket: nomor urut per event (1, 2, 3, ...), tidak pernah dipakai ulang
            $table->unsignedInteger('ticket_no');
            // aktif | hadir | dibatalkan
            $table->string('status', 12)->default('aktif');

            // Kunci unik yang dijaga database. NULL saat tiket dibatalkan (unique mengizinkan banyak NULL).
            $table->string('active_key', 40)->nullable()->unique();  // satu tiket aktif per akun per event
            $table->string('nim_key', 80)->nullable()->unique();     // satu NIM satu tiket aktif per event

            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'ticket_no']);
            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};

<?php

namespace Database\Seeders;

use App\Models\StudyProgram;
use Illuminate\Database\Seeder;

/**
 * Mengisi tabel study_programs dari database/seeders/data/study_programs.csv
 * (kolom: code,name). Tidak ada kolom fakultas.
 *
 * PENTING: isi CSV bawaan hanyalah CONTOH agar aplikasi bisa dicoba.
 * Ganti dengan daftar program studi UNS dari sumber resmi sebelum dipakai sungguhan.
 */
class StudyProgramSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/study_programs.csv');

        if (! is_readable($path)) {
            $this->command?->warn("File {$path} tidak ditemukan, seeder program studi dilewati.");

            return;
        }

        $handle = fopen($path, 'r');
        fgetcsv($handle); // lewati baris header

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2 || trim($row[0]) === '') {
                continue;
            }

            StudyProgram::updateOrCreate(
                ['code' => trim($row[0])],
                ['name' => trim($row[1])]
            );
        }

        fclose($handle);
    }
}

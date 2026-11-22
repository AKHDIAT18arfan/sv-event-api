# SV Event API

Backend pendaftaran event Sekolah Vokasi UNS (Laravel 11+, MySQL, Sanctum).

## Setup lokal
    composer install
    cp .env.example .env
    php artisan key:generate
    # isi DB_* di .env, buat database sv_event
    php artisan migrate --seed
    php artisan storage:link
    php artisan serve
    php artisan test

Akun contoh (hanya lokal, sandi: password): admin@svevent.test, panitia.d3ti@svevent.test, budi@svevent.test

## Dokumentasi
Lihat docs/PANDUAN_BACKEND.md (endpoint, aturan bisnis, deployment).

## Produksi
Jangan jalankan seeder akun contoh. Gunakan: php artisan db:seed --class=StudyProgramSeeder --force

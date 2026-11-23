# SV Event API

REST API untuk pendaftaran event kampus di Sekolah Vokasi UNS. Peserta mendaftar ke sesi event dan mendapat nomor tiket, panitia mencatat kehadiran, dan BEM SV mengelola akun panitia serta event.

Dibuat dengan Laravel dan MySQL. Front-End dikerjakan terpisah dan memakai API ini lewat token Bearer.

## Fitur

- Registrasi dan login akun (Laravel Sanctum)
- Katalog event dengan pencarian dan filter
- Pendaftaran per sesi, dengan jenis peserta Umum atau Mahasiswa yang dipilih saat mendaftar
- Kuota per sesi dan nomor tiket berurutan per event
- Satu akun dan satu NIM hanya boleh punya satu tiket aktif per event
- Pembatalan tiket sebelum sesi dimulai
- Presensi manual oleh panitia, termasuk data untuk cetak lembar presensi
- Panel SuperAdmin untuk mengelola akun panitia, event, dan akun peserta
- Event dan akun yang dihapus hanya disembunyikan (soft delete)

## Peran

| Peran | Yang bisa dilakukan |
|---|---|
| Peserta | Mendaftar event, melihat dan membatalkan tiket |
| Panitia | Membuat dan mengelola event miliknya, presensi |
| SuperAdmin | Kelola akun panitia, hapus event dan akun user |

## Teknologi

- PHP 8.2+ dan Laravel 11+
- MySQL 8 (InnoDB)
- Laravel Sanctum untuk autentikasi
- PHPUnit untuk pengujian

## Menjalankan di lokal

Butuh PHP 8.2+, Composer, dan MySQL.

1. Clone repo dan pasang dependensi:

   ```bash
   git clone https://github.com/AKHDIAT18arfan/sv-event-api.git
   cd sv-event-api
   composer install
   ```

2. Buat database:

   ```sql
   CREATE DATABASE sv_event CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. Salin file env, lalu isi `DB_USERNAME` dan `DB_PASSWORD`:

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. Migrasi dan isi data contoh:

   ```bash
   php artisan migrate --seed
   php artisan storage:link
   ```

5. Jalankan server:

   ```bash
   php artisan serve
   ```

API tersedia di `http://localhost:8000/api`. Semua request perlu header `Accept: application/json`, dan endpoint yang butuh login memakai `Authorization: Bearer <token>`.

Seeder membuat beberapa akun dan tiga event contoh. Data ini hanya untuk pengembangan di lokal, daftarnya ada di `database/seeders`.

## Pengujian

Tes memakai database terpisah. Buat dulu database `sv_event_test`, lalu atur koneksinya di `phpunit.xml`.

```bash
php artisan test
```

Untuk menguji rebutan kuota, ada skrip yang mendaftarkan banyak akun sekaligus ke satu sesi:

```bash
BASE_URL=http://localhost:8000 SESSION_ID=1 N=30 bash scripts/test-quota-race.sh
```

Skrip ini butuh `curl` dan `jq`, dan kuota sesinya harus lebih kecil dari `N`.

## Endpoint

Ada 32 endpoint. Ringkasannya:

| Kelompok | Contoh |
|---|---|
| Auth | `POST /api/auth/register`, `POST /api/auth/login`, `GET /api/auth/me` |
| Katalog | `GET /api/events`, `GET /api/events/{id}` |
| Peserta | `POST /api/sessions/{id}/registrations`, `GET /api/my/registrations` |
| Panitia | `/api/panitia/events`, `/api/events/{id}/participants` |
| SuperAdmin | `/api/admin/events`, `/api/admin/panitia`, `/api/admin/users` |

Daftar lengkap, format request dan respons, serta kode error ada di [`docs/PANDUAN_BACKEND.md`](docs/PANDUAN_BACKEND.md). Koleksi Postman untuk uji manual ada di [`docs/postman`](docs/postman).

## Struktur singkat

```
app/Services/       logika tiket, kuota, dan presensi
app/Http/           controller, request, dan resource
app/Policies/       aturan akses event
database/           migrasi dan seeder
tests/Feature/      tes aturan bisnis
scripts/            skrip uji rebutan kuota
docs/               panduan dan koleksi Postman
```

## Deployment

Panduan deploy ke VPS Ubuntu dengan Nginx dan PHP-FPM ada di bagian 13 `docs/PANDUAN_BACKEND.md`. Di produksi jangan jalankan seeder akun contoh. Cukup seeder program studi:

```bash
php artisan db:seed --class=StudyProgramSeeder --force
```

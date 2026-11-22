# Panduan Backend SV Event (Laravel + MySQL), Versi 2.1

Kerangka ini sudah disesuaikan dengan **desain UI final** dan keputusan terbaru: akun sama untuk semua, jenis peserta dipilih saat mendaftar event, akun panitia dibuat SuperAdmin, tanpa Draft, tanpa ekspor Excel, dan cetak presensi berupa PDF dari Front-End.

Isinya **kode khusus aplikasi** (migrasi, model, controller, aturan bisnis, seeder, tes), bukan seluruh proyek Laravel. Kamu membuat proyek Laravel kosong, lalu menyalin isi kerangka ke dalamnya.

> **Catatan jujur:** kode ini ditulis tanpa bisa dijalankan (lingkungan penyusunnya tidak punya PHP). Sudah diperiksa dengan teliti dan disertai 23 tes otomatis, tetapi **jalankan `php artisan test` dan uji manual lebih dulu**. Kalau ada error, perbaikannya biasanya kecil (versi paket, nama kelas). Bagian 12 berisi daftar hal yang perlu dicek.

## 1. Keputusan teknis

| Hal | Pilihan |
|---|---|
| Framework | Laravel 11 atau lebih baru, PHP 8.2+ |
| Database | MySQL (InnoDB) |
| Autentikasi | Laravel Sanctum, token Bearer. Bagi Front-End: `Authorization: Bearer <token>` |
| Format respons | `{ "success": true, "data": ... }` dan `{ "success": false, "error": { "code", "message" } }` |
| PDF presensi | Dibuat di Front-End (tampilan cetak, lalu Simpan sebagai PDF). Backend menyediakan datanya tanpa paginasi. Tidak ada paket tambahan |
| Hapus event dan akun | Soft delete: tampil hilang, data tetap tersimpan |

## 2. Prasyarat

PHP 8.2+ (ekstensi: mbstring, xml, curl, zip, gd, pdo_mysql), Composer, MySQL 8, dan Postman. Disarankan memakai Laragon (Windows) atau XAMPP.

## 3. Langkah setup

```bash
# 1. Buat proyek Laravel kosong
composer create-project laravel/laravel sv-event-api
cd sv-event-api

# 2. Pasang API + Sanctum (tidak ada paket tambahan lain)
php artisan install:api

# 3. Salin isi kerangka ke proyek (timpa app/Models/User.php bawaan)
#    app/  database/  routes/api.php  tests/  config/cors.php  scripts/
```

4. **Atur `.env`** memakai isi `.env.snippet` (database `sv_event`, timezone `Asia/Jakarta`, `FRONTEND_URL`). Buat database kosongnya: `CREATE DATABASE sv_event CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
5. **Gabungkan `bootstrap-app.snippet.php` ke `bootstrap/app.php`**: tambahkan alias middleware `role` dan penangan error JSON. Jangan menimpa seluruh file. Langkah ini wajib.
6. Jalankan:

```bash
php artisan migrate
php artisan db:seed          # prodi contoh, 5 akun contoh, 3 event contoh
php artisan storage:link     # agar poster event bisa diakses
php artisan serve            # http://localhost:8000
php artisan test             # jalankan 23 tes aturan bisnis
```

Akun contoh (kata sandi semuanya `password`, hapus sebelum deployment):

| Email | Peran |
|---|---|
| admin@svevent.test | SuperAdmin (BEM SV) |
| panitia.d3ti@svevent.test | Panitia D3 Teknik Informatika (pemilik SIF dan Technopreneur Academy) |
| panitia.himadepsi@svevent.test | Panitia HIMADEPSI (pemilik Career Talk BIOMETRIC) |
| budi@svevent.test, siti@svevent.test | Peserta |

## 4. Isi kerangka

| File | Fungsi |
|---|---|
| `database/migrations/*` | 6 tabel: study_programs, users (kolom tambahan), events, event_sessions, registrations, attendances |
| `app/Models/*` | Model dan relasi, konstanta status |
| `app/Services/TicketService.php` | **Inti aplikasi**: klaim tiket, ID tiket, kuota, batal, centang hadir |
| `app/Services/ParticipantFilter.php` | Daftar peserta dengan **JOIN 5 tabel** (registrations, users, event_sessions, study_programs, attendances); dipakai tabel presensi dan lembar cetak |
| `app/Services/EventStats.php` | Hitungan per sesi dan per program studi (JOIN) |
| `app/Policies/EventPolicy.php` | Panitia hanya mengakses event yang dibuat akunnya |
| `app/Http/Middleware/EnsureRole.php` | Pembatas peran (`role:panitia`) |
| `app/Http/Controllers/Api/*` | Auth, akun, katalog event, registrasi, event panitia, presensi, SuperAdmin |
| `app/Http/Requests/*`, `Resources/*` | Validasi berpesan Bahasa Indonesia dan bentuk JSON |
| `app/Exceptions/ApiException.php` | Error bisnis dengan kode (`QUOTA_FULL` dan lainnya) |
| `routes/api.php` | Semua endpoint |
| `database/seeders/*` | Prodi, akun contoh, tiga event contoh |
| `tests/Feature/RegistrationFlowTest.php` | 23 tes |
| `scripts/test-quota-race.sh` | Uji rebutan kuota serentak |
| `config/cors.php` | Izin akses dari alamat Front-End |

## 5. Aturan bisnis menjadi kode

| Aturan | Di mana dijaga |
|---|---|
| Event khusus Mahasiswa hanya menerima pilihan Mahasiswa; event terbuka (`SEMUA`) menerima Mahasiswa dan Umum | `TicketService::claim` (cek `target_peserta` dan `participant_type`) |
| Jenis peserta dipilih saat daftar event. Umum: nama dan telepon dari akun (tidak bisa diubah). Mahasiswa: wajib NIM dan program studi | `ClaimRequest` dan `claim` |
| Satu tiket aktif per akun per event | Cek di `claim` **dan** kolom unik `registrations.active_key` |
| Satu NIM satu tiket aktif per event | Cek di `claim` **dan** kolom unik `registrations.nim_key` (NIM dinormalkan huruf besar) |
| Kuota per sesi, tidak kurang dari 0 | `claim` mengunci baris event dan sesi (`lockForUpdate`) di dalam transaksi |
| Klaim hanya saat Dibuka dan dalam jadwal | `Event::isRegistrationOpen()` |
| ID tiket berurutan per event, tidak dipakai ulang | `events.last_ticket_no` dinaikkan di transaksi yang sama dengan pengurangan kuota. Pembatalan tidak menurunkannya, jadi nomor tertinggi bisa melebihi kuota |
| Batal hanya sebelum event dimulai dan belum hadir | `TicketService::cancel` (acuan: `start_at` sesi tiket) |
| Hadir hanya sekali, centang bisa dibatalkan | `markPresent`, `unmarkPresent`, kolom unik `attendances.registration_id` |
| Panitia hanya mengakses event miliknya | `EventPolicy::manage` (`events.created_by` = akun panitia). **SuperAdmin sengaja tidak punya akses presensi atau pengelolaan event** |
| Status event: Dibuka atau Ditutup (Selesai = Ditutup), tanpa Draft | `Event::STATUSES`; event baru langsung Dibuka |
| Hapus event | Panitia: event miliknya, soft delete. SuperAdmin: wajib alasan (min. 10 karakter), tercatat `deleted_reason` dan `deleted_by`. Tiket peserta untuk event itu ikut hilang dari Tiket Saya |
| Hapus akun panitia | Soft delete, ditolak (`HAS_ACTIVE_EVENTS`) bila masih punya event Dibuka |
| Hapus akun user | Soft delete. Tiket aktif yang sesinya belum dimulai dibatalkan otomatis dan kuotanya kembali. Email dibebaskan agar bisa dipakai mendaftar ulang |

Tambahan keputusan pada versi 2.1:

| Aturan | Di mana dijaga |
|---|---|
| Nama event maksimal 120 karakter | `EventRequest` |
| Poster wajib saat membuat event (PNG, JPG, WEBP, maks 5 MB). Saat mengubah boleh kosong dan poster lama tetap dipakai | `EventRequest` |
| Kata sandi minimal 8 karakter, ada huruf kapital, angka, dan simbol | `RegisterRequest`, `ChangePasswordRequest`, `StorePanitiaRequest` |
| Sesi yang sudah dimulai menolak pendaftaran | `TicketService::claim`, dan `SessionResource` (`has_started`, `is_open`) |
| Daftar presensi 20 baris per halaman (`per_page` boleh diatur, maks. 100) | `ParticipantController::index` |
| Registrasi akun dibatasi 10 permintaan per menit | `routes/api.php` |
| `TICKET_CANCELLED` selalu 409 | `TicketService` |

## 6. Beda dengan dokumen skema lama (perbarui ERD dan data dictionary)

Database sekarang **6 tabel** (masih memenuhi syarat minimal 5): `study_programs`, `users`, `events`, `event_sessions`, `registrations`, `attendances`.

| Perubahan | Alasan |
|---|---|
| Tabel `event_committees` dihapus | Akses panitia lewat `events.created_by` (akun penyelenggara) |
| `users`: hanya `name`, `email`, `phone`, `password`, `role`, `is_active`, `deleted_at` | NIM, prodi, dan jenis peserta pindah ke pendaftaran |
| `registrations` menyimpan `participant_type`, `nim`, `study_program_id` | Jenis peserta dipilih saat daftar event |
| `registrations.active_key`, `nim_key` | Menjaga aturan "satu tiket aktif per akun/NIM per event" di level database (MySQL tidak punya unique index parsial) |
| `registrations.ticket_no` | ID tiket berupa nomor urut per event |
| `events.target_peserta`: `MAHASISWA` atau `SEMUA` | Khusus mahasiswa atau terbuka |
| `events.organizer_name` teks bebas, tanpa `organizer_program_id` | Mendukung kolaborasi ("D3TI x HIMADEPSI") |
| `events.status`: `dibuka`, `ditutup` | Tanpa Draft; Selesai = Ditutup |
| `events.last_ticket_no`, `poster_path`, `deleted_reason`, `deleted_by`, `deleted_at` | Penghitung ID tiket, poster, soft delete dengan alasan |
| `attendances` (sebelumnya `gate_attendances`) | `checked_by`, `checked_at` |
| Tidak ada fakultas, `ticket_token`, kolom tamu | Semua peserta punya akun, hanya program studi |

Keterbatasan yang sengaja diterima: filter "Program Studi Penyelenggara" di Beranda bekerja sebagai **pencarian teks** pada nama penyelenggara (`?organizer=`), karena penyelenggara berupa teks bebas.

## 7. Daftar endpoint (32)

Semua dikirim dengan header `Accept: application/json`. Yang bertanda login memakai `Authorization: Bearer <token>`.

| Layar UI | Method dan path | Akses |
|---|---|---|
| Registrasi Akun | `POST /api/auth/register` (name, email, phone, password, password_confirmation) | Publik |
| Login | `POST /api/auth/login` | Publik |
| Navbar, form pendaftaran | `GET /api/auth/me` (memuat `registration_defaults` NIM dan prodi terakhir), `POST /api/auth/logout` | Login |
| Pengaturan Akun Saya | `PUT /api/account/profile` (name, phone), `PUT /api/account/password` | Login |
| Konfirmasi pendaftaran | `GET /api/study-programs?q=` | Publik |
| Beranda | `GET /api/events?q=&organizer=&target_peserta=&date=&status=&page=` | Publik |
| Detail Event | `GET /api/events/{id}` | Publik |
| Konfirmasi pendaftaran | `POST /api/sessions/{sessionId}/registrations` (participant_type; untuk MAHASISWA juga nim dan study_program_id) | Peserta |
| Tiket Saya | `GET /api/my/registrations?tab=aktif\|selesai` | Peserta |
| E-Pass | `GET /api/registrations/{id}` | Pemilik tiket |
| Batalkan pendaftaran | `POST /api/registrations/{id}/cancel` | Pemilik tiket |
| Event Program Studi (daftar) | `GET /api/panitia/events?status=` | Panitia |
| Form Event | `POST /api/panitia/events`, `GET`, `PUT /api/panitia/events/{id}` (multipart) | Panitia pemilik |
| Ubah Status Event | `PATCH /api/panitia/events/{id}/status` body `{ "status": "dibuka" \| "ditutup" }` | Panitia pemilik |
| Hapus Event (panitia) | `DELETE /api/panitia/events/{id}` | Panitia pemilik |
| Presensi | `GET /api/events/{id}/participants?q=&session_id=&study_program_id=&status=&page=` | Panitia pemilik |
| Presensi (centang) | `POST` dan `DELETE /api/events/{id}/participants/{registrationId}/attendance` | Panitia pemilik |
| Cetak Lembar Presensi | `GET /api/events/{id}/participants/print` (semua baris tanpa paginasi, maks. 3000) | Panitia pemilik |
| Rekap (opsional) | `GET /api/events/{id}/dashboard` (per sesi dan per program studi) | Panitia pemilik |
| Kelola Event Kampus | `GET /api/admin/events?q=&status=` (memuat `summary`), `DELETE /api/admin/events/{id}` body `{ "reason": "..." }` | SuperAdmin |
| Kelola Akun Panitia | `GET`, `POST /api/admin/panitia`, `DELETE /api/admin/panitia/{id}` | SuperAdmin |
| Akun User | `GET /api/admin/users?q=`, `GET /api/admin/users/{id}` (daftar tiket terdampak untuk modal), `DELETE /api/admin/users/{id}` | SuperAdmin |

**Kode error** (`error.code`): `VALIDATION_ERROR` (422, ada `details`), `INVALID_CREDENTIALS` (401/422), `UNAUTHORIZED` (401), `FORBIDDEN` (403), `NOT_FOUND` (404), `NOT_ELIGIBLE` (403), `REGISTRATION_CLOSED` (422), `ALREADY_REGISTERED` (409, untuk akun maupun NIM), `QUOTA_FULL` (409), `TICKET_CANCELLED` (409), `ALREADY_CHECKED_IN` (409), `CANNOT_CANCEL` (422), `INVALID_STATUS_TRANSITION` (422), `HAS_ACTIVE_EVENTS` (422).

Halaman 403 di Front-End muncul saat respons `FORBIDDEN`; 404 saat `NOT_FOUND`; respons 401 mengarahkan ke login.

## 8. Contoh request dan respons

**Daftar akun**

```http
POST /api/auth/register
{ "name": "Budi Santoso", "email": "budi@mail.com", "phone": "081234567890",
  "password": "Rahasia#123", "password_confirmation": "Rahasia#123" }
```

**Konfirmasi pendaftaran sebagai Mahasiswa** (`POST /api/sessions/5/registrations`):

```json
{ "participant_type": "MAHASISWA", "nim": "V3425001", "study_program_id": 1 }
```

Sebagai Umum cukup `{ "participant_type": "UMUM" }`. Respons 201:

```json
{
  "data": {
    "id": 31, "ticket_no": 23, "ticket_label": "#23", "status": "aktif", "can_cancel": true,
    "event": { "id": 2, "name": "Technopreneur Academy 2026" },
    "session": { "id": 5, "name": "Sesi Utama", "start_at": "2026-12-01T09:00:00+07:00" },
    "participant": { "name": "Budi Santoso", "type": "MAHASISWA", "nim": "V3425001", "study_program": "D3 Teknik Informatika", "identity": "V3425001" }
  },
  "success": true
}
```

**Kuota penuh**, respons 409: `{ "success": false, "error": { "code": "QUOTA_FULL", "message": "Kuota sesi sudah penuh." } }`

**Buat event** (`POST /api/panitia/events`, multipart/form-data, langsung berstatus Dibuka):

```
name=Seminar Contoh
organizer_name=D3TI x HIMADEPSI
venue=Aula Gedung C
target_peserta=SEMUA            (atau MAHASISWA)
registration_open_at=2026-12-01 08:00:00
registration_close_at=2026-12-10 23:59:00
poster=<file PNG/JPG/WEBP, maks 5 MB, wajib saat membuat event>
sessions[0][name]=Sesi Pagi
sessions[0][start_at]=2026-12-15 09:00:00
sessions[0][end_at]=2026-12-15 12:00:00
sessions[0][quota_total]=100
```

Untuk mengubah event dengan upload poster, kirim `POST` dengan field tambahan `_method=PUT` (PHP tidak membaca multipart di request PUT). Sesi yang sudah ada dikirim lagi dengan `sessions[0][id]`.

**Hapus event oleh SuperAdmin**: `DELETE /api/admin/events/9` body `{ "reason": "Event dibatalkan oleh penyelenggara" }`.

## 9. Urutan uji manual di Postman

1. Login `admin@svevent.test`. `GET /api/admin/events` menampilkan tiga event contoh dan `summary`.
2. Login `budi@svevent.test`, `GET /api/events`, lalu klaim sesi SIF (event `SEMUA`) sebagai UMUM. `ticket_no` harus 1.
3. Klaim lagi dengan akun yang sama: `ALREADY_REGISTERED`. Klaim event Technopreneur (khusus mahasiswa) sebagai UMUM: `NOT_ELIGIBLE`; sebagai MAHASISWA dengan NIM: berhasil.
4. Login `siti@svevent.test`, klaim event yang sama sebagai MAHASISWA dengan NIM yang sama: `ALREADY_REGISTERED`.
5. Batalkan tiket Budi, cek kuota kembali. Daftar lagi: `ticket_no` bukan 1.
6. Login `panitia.d3ti@svevent.test`, buka `GET /api/events/{id}/participants`, centang hadir, centang lagi (409), batal centang. Buka event milik HIMADEPSI: 403.
7. Login admin, `GET /api/events/{id}/participants`: harus 403 (SuperAdmin tidak punya presensi). Hapus event dengan dan tanpa alasan.

## 10. Rencana kerja

| Langkah | Isi | Epic | Perkiraan |
|---|---|---|---|
| A. Fondasi | Setup proyek (bagian 3), migrasi, seeder | EP1, EP3 | 0,5 hari |
| B. Autentikasi dan akun | Register, login, me, profil, ganti sandi, peran, penangan error | EP3 | 1 hari |
| C. Event panitia | Buat, ubah, status, hapus, upload poster, katalog publik | EP4 | 1,5 hari |
| D. Registrasi | `TicketService`, klaim, batal, Tiket Saya, E-Pass | EP5 | 2 hari |
| E. Presensi | Tabel peserta (JOIN), centang hadir, data cetak | EP6 | 1 hari |
| F. SuperAdmin | Kelola event (hapus + alasan), akun panitia, akun user | EP4, EP7 | 1 hari |
| G. Pengujian | `php artisan test`, `scripts/test-quota-race.sh`, Postman, perbaikan | EP8 | 1,5 hari |
| H. Deployment | VPS, HTTPS, koleksi Postman | EP8, EP9 | 1 hari |

Perkiraan untuk satu orang backend; bisa dibagi dua (B dan C paralel dengan D dan E setelah A).

## 11. Menguji rebutan kuota

```bash
# Buat sesi dengan kuota kecil (misal 10) pada event berjenis SEMUA, lalu:
BASE_URL=http://localhost:8000 SESSION_ID=1 N=30 bash scripts/test-quota-race.sh
```

Hasil yang benar: tepat 10 `BERHASIL`, 20 `QUOTA_FULL`, ID tiket 1 sampai 10 berurutan tanpa kembar. Hasil ini bisa menjadi bukti pengujian beban di laporan. `php artisan serve` memproses satu permintaan pada satu waktu, jadi uji yang bermakna sebaiknya dijalankan di Nginx + PHP-FPM (bagian 13).

## 12. Hal yang perlu dicek dan diputuskan

1. **Kode belum dijalankan.** Prioritaskan `php artisan test`. Kalau ada tes gagal, kirim pesan errornya untuk diperbaiki.
2. **Seeder program studi hanya contoh.** `database/seeders/data/study_programs.csv` berisi 7 prodi berkode `CONTOH-xx`. Ganti dengan daftar resmi program studi UNS dari sumber UNS (format `code,name`).
3. **Tanggal event contoh digeser** relatif terhadap hari ini supaya bisa diuji. Kuota, nama sesi, dan kategori peserta di seeder adalah ilustrasi, bukan data resmi.
4. **Status dua arah:** panitia boleh mengubah Dibuka menjadi Ditutup dan sebaliknya (membuka lagi). Jika hanya satu arah yang diinginkan, ubah `PanitiaEventController::updateStatus`.
5. **Event dihapus (soft delete):** tiket peserta untuk event itu ikut hilang dari Tiket Saya, data tetap ada di database dan bisa dipulihkan developer.
6. **Acuan "event dimulai"** adalah `start_at` sesi pada tiket. Sesi yang sudah dimulai menolak pendaftaran (`REGISTRATION_CLOSED`, 422) dan tampil Ditutup; respons sesi memuat `has_started` dan `is_open`. Tidak ada tombol tutup per sesi.
7. **Belum ada:** lupa kata sandi (di UI cukup tautan yang menampilkan pesan "hubungi admin BEM SV"), verifikasi email, dan pengiriman E-Pass lewat email. NIM dan email tidak diverifikasi ke sistem resmi UNS.
8. **Front-End:** "Ingat sesi saya" = simpan token di `localStorage` (permanen) atau `sessionStorage` (sampai tab ditutup). Cetak presensi = ambil `/participants/print`, tampilkan sebagai tabel dengan CSS `@media print`, lalu pilih Simpan sebagai PDF. Tanggal dikirim ISO 8601; format dd/mm/yyyy dan jam 24 dikerjakan Front-End.
9. **Keamanan sebelum produksi:** `APP_DEBUG=false`, akun contoh dihapus, `FRONTEND_URL` hanya alamat Front-End resmi, database tidak bisa diakses dari luar server.

## 13. Deployment ke VPS (Ubuntu)

```bash
sudo apt update && sudo apt install -y nginx mysql-server php8.3-fpm php8.3-{mysql,xml,mbstring,curl,zip,gd,intl,bcmath} composer certbot python3-certbot-nginx unzip git
# unggah proyek ke /var/www/sv-event-api, lalu:
cd /var/www/sv-event-api
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate   # lalu isi .env (APP_ENV=production, APP_DEBUG=false, DB_*, FRONTEND_URL, APP_URL)
php artisan migrate --force
php artisan db:seed --class=StudyProgramSeeder --force   # JANGAN seed akun contoh di produksi
php artisan storage:link
sudo chown -R www-data:www-data storage bootstrap/cache
php artisan config:cache && php artisan route:cache
```

Buat akun SuperAdmin pertama lewat `php artisan tinker` (role `super_admin`, kata sandi kuat). Server block Nginx:

```nginx
server {
    server_name api.domainmu.id;
    root /var/www/sv-event-api/public;
    index index.php;
    client_max_body_size 6M;   # untuk upload poster (maks 5 MB)

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
```

Lalu `sudo certbot --nginx -d api.domainmu.id` untuk HTTPS. Setelah deploy, jalankan lagi `scripts/test-quota-race.sh` memakai `BASE_URL` server.

## 14. Masalah umum

| Gejala | Penyebab dan solusi |
|---|---|
| `Target class [role] does not exist` | Alias middleware belum ditambahkan ke `bootstrap/app.php` (langkah 5) |
| Error tampil sebagai halaman HTML, bukan JSON | Penangan error belum ditambahkan, atau request tanpa `Accept: application/json` |
| Poster tidak tampil | Jalankan `php artisan storage:link` dan pastikan `APP_URL` benar |
| `SQLSTATE ... foreign key` saat migrate | Urutan migrasi terbalik atau tabel lama masih ada; jalankan `php artisan migrate:fresh --seed` di lingkungan pengembangan |
| Browser memblokir request (CORS) | `FRONTEND_URL` belum sesuai alamat Front-End, atau `config/cors.php` belum disalin |
| Dua pendaftar mendapat nomor kembar | Seharusnya mustahil; pastikan tabel memakai InnoDB dan `claim` berjalan di dalam `DB::transaction` |

## 15. Catatan untuk Front-End (sisa desain dan keputusan sementara)

| Hal di desain | Keputusan |
|---|---|
| Banner "Persyaratan Kategori Khusus" dan kalimat "Akunmu terdaftar sebagai Umum" | Sisa desain. Backend tidak menyimpan jenis peserta di akun. Hapus bila sempat |
| Teks "barcode" di pesan sukses daftar dan label "VARIASI 2" | Sisa desain. Presensi manual lewat ID tiket |
| Modal Hapus Event menyebut "permanen" | Backend soft delete (data tetap tersimpan). Ubah kalimatnya, misalnya "Event disembunyikan, datanya tetap tersimpan" |
| Header panel SuperAdmin tertulis "PANITIA - D3 Teknik Informatika" | Tampilkan `name` dari `GET /api/auth/me` |
| Sertifikat, narahubung, alamat lengkap, lokasi per sesi, tag sesi, badge sesi | Statis sementara. Kumpulkan di satu file konstanta agar mudah diganti nanti |
| Kode event (SV-EVT-2026-0921) | Hitung di Front-End, misalnya `SV-EVT-2026-` + id event (4 digit) |
| Lokasi per sesi | Pakai `venue` event atau sembunyikan, jangan ditulis statis per sesi |
| Sesi ditutup (Sesi 3) | Pakai `has_started` dan `is_open` dari respons sesi, digabung status dan jadwal event |
| Deskripsi event berformat (tebal, miring, daftar) | Backend menyimpan teks apa adanya. Front-End yang membersihkan (sanitize) saat menampilkan |
| Presensi "1-6 dari 38" | Backend 20 per halaman. Baca info halaman dari respons |

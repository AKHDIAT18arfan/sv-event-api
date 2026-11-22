<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventSession;
use App\Models\Registration;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Menguji aturan bisnis utama: kuota, ID tiket berurutan, satu tiket per akun dan per NIM,
 * kelayakan event, pembatalan, checklist kehadiran, serta hak akses tiap peran.
 *
 * Jalankan: php artisan test --filter=RegistrationFlowTest
 */
class RegistrationFlowTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function user(string $role, ?string $name = null): User
    {
        $this->counter++;

        return User::create([
            'name' => $name ?? ucfirst($role)." {$this->counter}",
            'email' => "{$role}{$this->counter}@test.id",
            'phone' => '08123456'.str_pad((string) $this->counter, 4, '0', STR_PAD_LEFT),
            'password' => 'password',
            'role' => $role,
        ]);
    }

    private function prodi(): StudyProgram
    {
        return StudyProgram::firstOrCreate(['code' => 'T1'], ['name' => 'D3 Teknik Informatika']);
    }

    /** @return array{0:Event,1:EventSession} */
    private function makeEvent(User $owner, string $target = 'SEMUA', int $quota = 2, ?\DateTimeInterface $start = null): array
    {
        $event = Event::create([
            'name' => 'Event Uji',
            'organizer_name' => 'Panitia Uji',
            'venue' => 'Aula',
            'target_peserta' => $target,
            'registration_open_at' => now()->subDay(),
            'registration_close_at' => now()->addDays(7),
            'status' => 'dibuka',
            'created_by' => $owner->id,
        ]);

        $start ??= now()->addDays(3);

        $session = $event->sessions()->create([
            'name' => 'Sesi 1',
            'start_at' => $start,
            'end_at' => Carbon::parse($start)->addHours(3),
            'quota_total' => $quota,
            'quota_remaining' => $quota,
        ]);

        return [$event, $session];
    }

    private function claim(EventSession $session, string $type = 'UMUM', ?string $nim = null)
    {
        $body = ['participant_type' => $type];
        if ($type === 'MAHASISWA') {
            $body['nim'] = $nim ?? 'V3425001';
            $body['study_program_id'] = $this->prodi()->id;
        }

        return $this->postJson("/api/sessions/{$session->id}/registrations", $body);
    }

    public function test_klaim_berhasil_dan_id_tiket_berurutan(): void
    {
        [, $session] = $this->makeEvent($this->user('panitia'), quota: 5);

        Sanctum::actingAs($this->user('peserta'));
        $this->claim($session, 'UMUM')
            ->assertCreated()
            ->assertJsonPath('data.ticket_no', 1)
            ->assertJsonPath('data.ticket_label', '#1')
            ->assertJsonPath('data.participant.study_program', 'Umum');

        Sanctum::actingAs($this->user('peserta'));
        $this->claim($session, 'MAHASISWA', 'V3425001')
            ->assertCreated()
            ->assertJsonPath('data.ticket_no', 2)
            ->assertJsonPath('data.participant.study_program', 'D3 Teknik Informatika');

        $this->assertSame(3, $session->fresh()->quota_remaining);
    }

    public function test_kuota_penuh_ditolak(): void
    {
        [, $session] = $this->makeEvent($this->user('panitia'), quota: 1);

        Sanctum::actingAs($this->user('peserta'));
        $this->claim($session)->assertCreated();

        Sanctum::actingAs($this->user('peserta'));
        $this->claim($session)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'QUOTA_FULL');

        $this->assertSame(0, $session->fresh()->quota_remaining);
    }

    public function test_satu_akun_hanya_satu_tiket_aktif_per_event(): void
    {
        [, $session] = $this->makeEvent($this->user('panitia'), quota: 5);
        Sanctum::actingAs($this->user('peserta'));

        $this->claim($session)->assertCreated();
        $this->claim($session)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'ALREADY_REGISTERED');
    }

    public function test_satu_nim_hanya_satu_tiket_aktif_per_event(): void
    {
        [, $session] = $this->makeEvent($this->user('panitia'), quota: 5);

        Sanctum::actingAs($this->user('peserta'));
        $this->claim($session, 'MAHASISWA', 'V3425001')->assertCreated();

        // akun lain mencoba memakai NIM yang sama
        Sanctum::actingAs($this->user('peserta'));
        $this->claim($session, 'MAHASISWA', 'v3425001')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'ALREADY_REGISTERED');
    }

    public function test_event_khusus_mahasiswa_menolak_pilihan_umum(): void
    {
        [, $session] = $this->makeEvent($this->user('panitia'), target: 'MAHASISWA');

        Sanctum::actingAs($this->user('peserta'));
        $this->claim($session, 'UMUM')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'NOT_ELIGIBLE');

        $this->claim($session, 'MAHASISWA')->assertCreated();
    }

    public function test_mahasiswa_wajib_mengisi_nim_dan_prodi(): void
    {
        [, $session] = $this->makeEvent($this->user('panitia'));

        Sanctum::actingAs($this->user('peserta'));
        $this->postJson("/api/sessions/{$session->id}/registrations", ['participant_type' => 'MAHASISWA'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.nim.0', 'NIM wajib diisi.');
    }

    public function test_pendaftaran_ditolak_jika_event_ditutup(): void
    {
        [$event, $session] = $this->makeEvent($this->user('panitia'));
        $event->update(['status' => 'ditutup']);

        Sanctum::actingAs($this->user('peserta'));
        $this->claim($session)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'REGISTRATION_CLOSED');
    }

    public function test_batal_mengembalikan_kuota_id_tidak_dipakai_ulang_dan_nim_bebas(): void
    {
        [, $session] = $this->makeEvent($this->user('panitia'), quota: 1);

        Sanctum::actingAs($this->user('peserta'));
        $id = $this->claim($session, 'MAHASISWA', 'V3425001')->json('data.id');

        $this->postJson("/api/registrations/{$id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'dibatalkan');
        $this->assertSame(1, $session->fresh()->quota_remaining);

        // NIM yang sama boleh dipakai lagi; ID tiket berikutnya 2, bukan 1
        Sanctum::actingAs($this->user('peserta'));
        $this->claim($session, 'MAHASISWA', 'V3425001')
            ->assertCreated()
            ->assertJsonPath('data.ticket_no', 2);
    }

    public function test_batal_ditolak_setelah_sesi_dimulai(): void
    {
        [, $session] = $this->makeEvent($this->user('panitia'), start: now()->addMinute());

        Sanctum::actingAs($this->user('peserta'));
        $id = $this->claim($session)->json('data.id');

        $this->travel(2)->minutes();

        $this->postJson("/api/registrations/{$id}/cancel")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'CANNOT_CANCEL');
    }

    public function test_panitia_mencentang_hadir_dan_membatalkan_centang(): void
    {
        $panitia = $this->user('panitia');
        [$event, $session] = $this->makeEvent($panitia, quota: 5);

        Sanctum::actingAs($this->user('peserta'));
        $regId = $this->claim($session)->json('data.id');

        Sanctum::actingAs($panitia);
        $this->postJson("/api/events/{$event->id}/participants/{$regId}/attendance")
            ->assertOk()
            ->assertJsonPath('data.status', 'hadir');

        $this->postJson("/api/events/{$event->id}/participants/{$regId}/attendance")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'ALREADY_CHECKED_IN');

        $this->deleteJson("/api/events/{$event->id}/participants/{$regId}/attendance")
            ->assertOk()
            ->assertJsonPath('data.status', 'aktif');
    }

    public function test_tabel_presensi_menampilkan_nim_untuk_mahasiswa_dan_telepon_untuk_umum(): void
    {
        $panitia = $this->user('panitia');
        [$event, $session] = $this->makeEvent($panitia, quota: 5);

        Sanctum::actingAs($this->user('peserta'));
        $this->claim($session, 'MAHASISWA', 'V3425001')->assertCreated();

        $umum = $this->user('peserta');
        Sanctum::actingAs($umum);
        $this->claim($session, 'UMUM')->assertCreated();

        Sanctum::actingAs($panitia);
        $res = $this->getJson("/api/events/{$event->id}/participants")->assertOk();

        $this->assertSame('V3425001', $res->json('data.0.participant.identity'));
        $this->assertSame($umum->phone, $res->json('data.1.participant.identity'));
        $this->assertSame(2, $res->json('summary.total_active'));
    }

    public function test_tiket_yang_sudah_hadir_tidak_bisa_dibatalkan(): void
    {
        $panitia = $this->user('panitia');
        [$event, $session] = $this->makeEvent($panitia, quota: 5);

        $peserta = $this->user('peserta');
        Sanctum::actingAs($peserta);
        $regId = $this->claim($session)->json('data.id');

        Sanctum::actingAs($panitia);
        $this->postJson("/api/events/{$event->id}/participants/{$regId}/attendance")->assertOk();

        Sanctum::actingAs($peserta);
        $this->postJson("/api/registrations/{$regId}/cancel")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'CANNOT_CANCEL');
    }

    public function test_panitia_hanya_mengakses_event_miliknya_dan_superadmin_tidak_punya_presensi(): void
    {
        $panitiaA = $this->user('panitia');
        $panitiaB = $this->user('panitia');
        [$eventA] = $this->makeEvent($panitiaA);

        Sanctum::actingAs($panitiaA);
        $this->getJson("/api/events/{$eventA->id}/participants")->assertOk();

        Sanctum::actingAs($panitiaB);
        $this->getJson("/api/events/{$eventA->id}/participants")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        Sanctum::actingAs($this->user('super_admin'));
        $this->getJson("/api/events/{$eventA->id}/participants")->assertStatus(403);
    }

    public function test_panitia_membuat_event_langsung_dibuka_lalu_menutupnya(): void
    {
        $panitia = $this->user('panitia');
        Sanctum::actingAs($panitia);

        Storage::fake('public');
        $fmt = 'Y-m-d H:i:s';
        $res = $this->withHeader('Accept', 'application/json')->post('/api/panitia/events', [
            'poster' => UploadedFile::fake()->image('poster.jpg', 1600, 900),
            'name' => 'Seminar Uji',
            'organizer_name' => 'D3TI x HIMADEPSI',
            'venue' => 'Aula Gedung C',
            'target_peserta' => 'SEMUA',
            'registration_open_at' => now()->subHour()->format($fmt),
            'registration_close_at' => now()->addDays(7)->format($fmt),
            'sessions' => [[
                'name' => 'Sesi Pagi',
                'start_at' => now()->addDays(3)->format($fmt),
                'end_at' => now()->addDays(3)->addHours(3)->format($fmt),
                'quota_total' => 10,
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'dibuka')
            ->assertJsonPath('data.organizer', 'D3TI x HIMADEPSI');

        $eventId = $res->json('data.id');
        $sessionId = $res->json('data.sessions.0.id');

        $this->patchJson("/api/panitia/events/{$eventId}/status", ['status' => 'ditutup'])
            ->assertOk()
            ->assertJsonPath('data.status', 'ditutup');

        Sanctum::actingAs($this->user('peserta'));
        $this->postJson("/api/sessions/{$sessionId}/registrations", ['participant_type' => 'UMUM'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'REGISTRATION_CLOSED');
    }

    public function test_superadmin_menghapus_event_wajib_alasan_dan_event_disembunyikan(): void
    {
        [$event] = $this->makeEvent($this->user('panitia'));

        Sanctum::actingAs($this->user('super_admin'));
        $this->deleteJson("/api/admin/events/{$event->id}", [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->deleteJson("/api/admin/events/{$event->id}", ['reason' => 'Event dibatalkan oleh penyelenggara'])
            ->assertOk();

        $this->getJson("/api/events/{$event->id}")->assertStatus(404);

        // datanya tetap tersimpan
        $stored = Event::withTrashed()->find($event->id);
        $this->assertNotNull($stored);
        $this->assertSame('Event dibatalkan oleh penyelenggara', $stored->deleted_reason);
    }

    public function test_hapus_akun_panitia_diblokir_selama_masih_punya_event_dibuka(): void
    {
        $panitia = $this->user('panitia');
        [$event] = $this->makeEvent($panitia);

        Sanctum::actingAs($this->user('super_admin'));
        $this->deleteJson("/api/admin/panitia/{$panitia->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'HAS_ACTIVE_EVENTS');

        $event->update(['status' => 'ditutup']);

        $this->deleteJson("/api/admin/panitia/{$panitia->id}")->assertOk();
    }

    public function test_hapus_akun_user_membatalkan_tiket_yang_belum_dimulai(): void
    {
        [, $session] = $this->makeEvent($this->user('panitia'), quota: 3);

        $peserta = $this->user('peserta');
        $email = $peserta->email;
        Sanctum::actingAs($peserta);
        $this->claim($session)->assertCreated();
        $this->assertSame(2, $session->fresh()->quota_remaining);

        Sanctum::actingAs($this->user('super_admin'));
        $this->deleteJson("/api/admin/users/{$peserta->id}")
            ->assertOk()
            ->assertJsonPath('data.cancelled_tickets', 1);

        $this->assertSame(3, $session->fresh()->quota_remaining);

        $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'password'])
            ->assertStatus(401);
    }

    public function test_ganti_kata_sandi(): void
    {
        $peserta = $this->user('peserta');
        Sanctum::actingAs($peserta);

        $this->putJson('/api/account/password', [
            'current_password' => 'salah',
            'password' => 'SandiBaru#123',
            'password_confirmation' => 'SandiBaru#123',
        ])->assertStatus(422)->assertJsonPath('error.code', 'INVALID_CREDENTIALS');

        $this->putJson('/api/account/password', [
            'current_password' => 'password',
            'password' => 'SandiBaru#123',
            'password_confirmation' => 'SandiBaru#123',
        ])->assertOk();

        $this->postJson('/api/auth/login', ['email' => $peserta->email, 'password' => 'SandiBaru#123'])
            ->assertOk();
    }

    public function test_register_akun_hanya_butuh_nama_telepon_email_dan_sandi(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Ani',
            'email' => 'ani@test.id',
            'phone' => '081200000000',
            'password' => 'Rahasia#123',
            'password_confirmation' => 'Rahasia#123',
        ])->assertCreated()
            ->assertJsonPath('data.user.role', 'peserta');

        $this->postJson('/api/auth/register', [
            'name' => 'Budi',
            'email' => 'budi@test.id',
            'password' => 'Rahasia#123',
            'password_confirmation' => 'Rahasia#123',
        ])->assertStatus(422)
            ->assertJsonPath('error.details.phone.0', 'Nomor telepon wajib diisi.');
    }

    public function test_pendaftaran_ditolak_jika_sesi_sudah_dimulai(): void
    {
        [, $session] = $this->makeEvent($this->user('panitia'), start: now()->addMinute());

        $this->travel(2)->minutes();

        Sanctum::actingAs($this->user('peserta'));
        $this->claim($session)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'REGISTRATION_CLOSED');
        $this->assertSame(2, $session->fresh()->quota_remaining);
    }

    public function test_poster_wajib_saat_membuat_event_dan_nama_maksimal_120_karakter(): void
    {
        Sanctum::actingAs($this->user('panitia'));
        $fmt = 'Y-m-d H:i:s';

        $this->postJson('/api/panitia/events', [
            'name' => str_repeat('A', 121),
            'organizer_name' => 'Panitia Uji',
            'venue' => 'Aula',
            'target_peserta' => 'SEMUA',
            'registration_open_at' => now()->subHour()->format($fmt),
            'registration_close_at' => now()->addDays(7)->format($fmt),
            'sessions' => [[
                'name' => 'Sesi 1',
                'start_at' => now()->addDays(3)->format($fmt),
                'end_at' => now()->addDays(3)->addHours(3)->format($fmt),
                'quota_total' => 10,
            ]],
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.poster.0', 'Poster banner wajib diunggah.')
            ->assertJsonPath('error.details.name.0', 'Nama event maksimal 120 karakter.');
    }

    public function test_sandi_wajib_huruf_kapital_angka_dan_simbol(): void
    {
        $body = fn (string $password) => [
            'name' => 'Cici',
            'email' => 'cici@test.id',
            'phone' => '081200000001',
            'password' => $password,
            'password_confirmation' => $password,
        ];

        $this->postJson('/api/auth/register', $body('rahasia123'))
            ->assertStatus(422)
            ->assertJsonPath('error.details.password.0', 'Kata sandi harus mengandung huruf kapital, angka, dan simbol.');

        $this->postJson('/api/auth/register', $body('Rahasia#123'))->assertCreated();
    }
}

<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Tiga event contoh (ER-71). Nama, penyelenggara, dan lokasi mengikuti poster resmi.
 *
 * Yang BUKAN data resmi: kuota, nama sesi, kategori peserta, dan tanggal.
 * Tanggal digeser relatif terhadap hari ini supaya pendaftaran bisa langsung diuji.
 */
class DemoEventSeeder extends Seeder
{
    public function run(): void
    {
        $d3ti = User::where('email', 'panitia.d3ti@svevent.test')->firstOrFail();
        $himadepsi = User::where('email', 'panitia.himadepsi@svevent.test')->firstOrFail();

        $events = [
            [
                'name' => 'Smart IT Festival (SIF) ke-21',
                'organizer_name' => 'D3 Teknik Informatika & D4 STARTIKA',
                'venue' => 'Balai Kota Surakarta',
                'target_peserta' => 'SEMUA',
                'owner' => $d3ti,
                'sessions' => [['Hari 1', 7, 100], ['Hari 2', 8, 100]],
            ],
            [
                'name' => 'Technopreneur Academy 2026',
                'organizer_name' => 'D3 Teknik Informatika & D4 STARTIKA',
                'venue' => 'Aula Lt.5 Gedung C FMIPA UNS',
                'target_peserta' => 'MAHASISWA',
                'owner' => $d3ti,
                'sessions' => [['Sesi Utama', 10, 80]],
            ],
            [
                'name' => 'Career Talk BIOMETRIC 2026',
                'organizer_name' => 'HIMADEPSI',
                'venue' => 'UNS INN Ballroom Indraprastha Lt.2',
                'target_peserta' => 'MAHASISWA',
                'owner' => $himadepsi,
                'sessions' => [['Sesi Pagi', 12, 120]],
            ],
        ];

        foreach ($events as $data) {
            $event = Event::updateOrCreate(['name' => $data['name']], [
                'description' => 'Event contoh untuk pengujian. Bukan data resmi penyelenggara.',
                'venue' => $data['venue'],
                'target_peserta' => $data['target_peserta'],
                'organizer_name' => $data['organizer_name'],
                'registration_open_at' => now()->subDay(),
                'registration_close_at' => now()->addDays(14),
                'status' => 'dibuka',
                'created_by' => $data['owner']->id,
            ]);

            if ($event->sessions()->exists()) {
                continue;
            }

            foreach ($data['sessions'] as [$name, $daysAhead, $quota]) {
                $start = now()->addDays($daysAhead)->setTime(9, 0);

                $event->sessions()->create([
                    'name' => $name,
                    'start_at' => $start,
                    'end_at' => $start->copy()->addHours(4),
                    'quota_total' => $quota,
                    'quota_remaining' => $quota,
                ]);
            }
        }
    }
}

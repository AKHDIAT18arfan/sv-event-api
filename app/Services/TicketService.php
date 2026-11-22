<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Seluruh logika kuota, ID tiket, pembatalan, dan kehadiran ada di sini.
 *
 * Urutan penguncian baris dibuat tetap supaya tidak terjadi deadlock:
 *   klaim      : event -> sesi
 *   batal      : registrasi -> sesi
 *   kehadiran  : registrasi
 */
class TicketService
{
    /**
     * Klaim tiket. Aturan bisnis 1 sampai 4 dan pembuatan ID tiket (nomor urut per event).
     *
     * @param array{participant_type:string,nim?:?string,study_program_id?:?int} $data
     */
    public function claim(User $user, EventSession $session, array $data): Registration
    {
        if ($user->role !== User::ROLE_PESERTA) {
            throw new ApiException('FORBIDDEN', 'Hanya akun peserta yang dapat mendaftar event.', 403);
        }

        return DB::transaction(function () use ($user, $session, $data) {
            // Kunci event dan sesi. Pendaftar lain untuk event yang sama menunggu di sini,
            // jadi ID tiket tidak kembar dan kuota tidak bisa terlampaui.
            $event = Event::whereKey($session->event_id)->lockForUpdate()->firstOrFail();
            $lockedSession = EventSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

            // Aturan 4: status Dibuka dan di dalam jadwal pendaftaran
            if (! $event->isRegistrationOpen()) {
                throw new ApiException('REGISTRATION_CLOSED', 'Pendaftaran event ini sedang ditutup.', 422);
            }

            // Sesi yang sudah dimulai tidak menerima pendaftaran (tampil sebagai Ditutup)
            if ($lockedSession->hasStarted()) {
                throw new ApiException('REGISTRATION_CLOSED', 'Sesi ini sudah dimulai, pendaftaran ditutup.', 422);
            }

            // Aturan 1: event khusus mahasiswa hanya menerima pilihan Mahasiswa.
            // Event terbuka (SEMUA) menerima Mahasiswa maupun Umum.
            $type = $data['participant_type'];
            if ($event->target_peserta === 'MAHASISWA' && $type !== Registration::TYPE_MAHASISWA) {
                throw new ApiException('NOT_ELIGIBLE', 'Event ini khusus Mahasiswa.', 403);
            }

            // Aturan 2a: satu tiket aktif per akun per event
            $alreadyRegistered = Registration::query()
                ->where('registrations.event_id', $event->id)
                ->where('registrations.user_id', $user->id)
                ->active()
                ->exists();

            if ($alreadyRegistered) {
                throw new ApiException('ALREADY_REGISTERED', 'Kamu sudah terdaftar di event ini.', 409);
            }

            // Aturan 2b: satu NIM satu tiket aktif per event (mencegah satu NIM dipakai banyak akun)
            $nim = null;
            $studyProgramId = null;
            if ($type === Registration::TYPE_MAHASISWA) {
                $nim = strtoupper(trim((string) $data['nim']));
                $studyProgramId = $data['study_program_id'];

                $nimTaken = Registration::query()
                    ->where('registrations.event_id', $event->id)
                    ->where('registrations.nim', $nim)
                    ->active()
                    ->exists();

                if ($nimTaken) {
                    throw new ApiException('ALREADY_REGISTERED', 'NIM ini sudah terdaftar di event ini.', 409);
                }
            }

            // Aturan 3: kuota tidak boleh kurang dari 0
            if ($lockedSession->quota_remaining < 1) {
                throw new ApiException('QUOTA_FULL', 'Kuota sesi sudah penuh.', 409);
            }

            // ID tiket: nomor urut per event, naik terus, tidak dipakai ulang walau tiket dibatalkan
            $ticketNo = $event->last_ticket_no + 1;
            $event->forceFill(['last_ticket_no' => $ticketNo])->save();

            $lockedSession->decrement('quota_remaining');

            return Registration::create([
                'event_id' => $event->id,
                'session_id' => $lockedSession->id,
                'user_id' => $user->id,
                'participant_type' => $type,
                'nim' => $nim,
                'study_program_id' => $studyProgramId,
                'ticket_no' => $ticketNo,
                'status' => Registration::AKTIF,
                'active_key' => Registration::activeKey($user->id, $event->id),
                'nim_key' => $nim ? Registration::nimKey($event->id, $nim) : null,
            ]);
        });
    }

    /**
     * Peserta membatalkan tiketnya. Aturan bisnis 7: hanya sebelum event dimulai dan hanya jika belum hadir.
     * Acuan "event dimulai" adalah start_at sesi pada tiket tersebut.
     */
    public function cancel(User $user, Registration $registration): Registration
    {
        return DB::transaction(function () use ($user, $registration) {
            $locked = Registration::whereKey($registration->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->user_id !== (int) $user->id) {
                throw new ApiException('FORBIDDEN', 'Tiket ini bukan milikmu.', 403);
            }
            if ($locked->status === Registration::DIBATALKAN) {
                throw new ApiException('TICKET_CANCELLED', 'Tiket ini sudah dibatalkan.', 409);
            }
            if ($locked->status === Registration::HADIR) {
                throw new ApiException('CANNOT_CANCEL', 'Tiket yang sudah hadir tidak bisa dibatalkan.', 422);
            }

            $session = EventSession::whereKey($locked->session_id)->lockForUpdate()->firstOrFail();

            if ($session->hasStarted()) {
                throw new ApiException('CANNOT_CANCEL', 'Pendaftaran tidak bisa dibatalkan karena event sudah dimulai.', 422);
            }

            $this->release($locked, $session);

            return $locked;
        });
    }

    /**
     * Dipakai saat SuperAdmin menghapus akun user: membatalkan tiket aktif yang sesinya belum dimulai.
     * Tiket yang sudah hadir atau sesinya sudah lewat dibiarkan sebagai riwayat.
     *
     * @return bool true bila tiket dibatalkan
     */
    public function cancelBySystem(Registration $registration): bool
    {
        return DB::transaction(function () use ($registration) {
            $locked = Registration::whereKey($registration->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== Registration::AKTIF) {
                return false;
            }

            $session = EventSession::whereKey($locked->session_id)->lockForUpdate()->firstOrFail();

            if ($session->hasStarted()) {
                return false;
            }

            $this->release($locked, $session);

            return true;
        });
    }

    /** Membatalkan tiket, melepas kunci unik, dan mengembalikan kuota. ID tiket tidak dipakai ulang. */
    private function release(Registration $registration, EventSession $session): void
    {
        $registration->forceFill([
            'status' => Registration::DIBATALKAN,
            'cancelled_at' => now(),
            'active_key' => null,
            'nim_key' => null,
        ])->save();

        $session->increment('quota_remaining');
    }

    /** Panitia mencentang peserta hadir. Aturan bisnis 5. */
    public function markPresent(Event $event, Registration $registration, User $checker): Registration
    {
        return DB::transaction(function () use ($event, $registration, $checker) {
            $locked = Registration::whereKey($registration->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->event_id !== (int) $event->id) {
                throw new ApiException('NOT_FOUND', 'Tiket tidak ditemukan di event ini.', 404);
            }
            if ($locked->status === Registration::DIBATALKAN) {
                throw new ApiException('TICKET_CANCELLED', 'Tiket ini sudah dibatalkan.', 409);
            }
            if ($locked->status === Registration::HADIR) {
                throw new ApiException('ALREADY_CHECKED_IN', 'Peserta ini sudah ditandai hadir.', 409);
            }

            Attendance::create([
                'registration_id' => $locked->id,
                'checked_by' => $checker->id,
                'checked_at' => now(),
            ]);

            $locked->forceFill(['status' => Registration::HADIR])->save();

            return $locked;
        });
    }

    /** Membatalkan centang hadir (salah centang). */
    public function unmarkPresent(Event $event, Registration $registration): Registration
    {
        return DB::transaction(function () use ($event, $registration) {
            $locked = Registration::whereKey($registration->id)->lockForUpdate()->firstOrFail();

            if ((int) $locked->event_id !== (int) $event->id) {
                throw new ApiException('NOT_FOUND', 'Tiket tidak ditemukan di event ini.', 404);
            }
            if ($locked->status !== Registration::HADIR) {
                throw new ApiException('INVALID_STATUS_TRANSITION', 'Peserta ini belum ditandai hadir.', 422);
            }

            Attendance::where('registration_id', $locked->id)->delete();
            $locked->forceFill(['status' => Registration::AKTIF])->save();

            return $locked;
        });
    }
}

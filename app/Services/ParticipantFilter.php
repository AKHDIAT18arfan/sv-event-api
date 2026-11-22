<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Builder;

/**
 * Daftar peserta untuk tabel Presensi dan lembar cetak.
 *
 * Memakai JOIN sungguhan antar tabel (registrations, users, event_sessions,
 * study_programs, attendances), yang juga memenuhi syarat "query JOIN" dari dosen.
 * Tabel Presensi dan lembar cetak memakai query yang sama, jadi isinya selalu konsisten.
 */
class ParticipantFilter
{
    /**
     * @param array{q?:string,session_id?:int|string,study_program_id?:int|string,status?:string,include_cancelled?:mixed} $filters
     */
    public function query(Event $event, array $filters): Builder
    {
        $query = Registration::query()
            ->select('registrations.*')
            ->join('users', 'users.id', '=', 'registrations.user_id')
            ->join('event_sessions', 'event_sessions.id', '=', 'registrations.session_id')
            ->leftJoin('study_programs', 'study_programs.id', '=', 'registrations.study_program_id')
            ->leftJoin('attendances', 'attendances.registration_id', '=', 'registrations.id')
            ->where('registrations.event_id', $event->id)
            ->with(['user', 'session', 'studyProgram', 'attendance']);

        // Tiket dibatalkan disembunyikan secara default
        $includeCancelled = filter_var($filters['include_cancelled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (! $includeCancelled) {
            $query->whereIn('registrations.status', Registration::ACTIVE_STATUSES);
        }

        if (! empty($filters['status'])) {
            match ($filters['status']) {
                'hadir' => $query->where('registrations.status', Registration::HADIR),
                'belum_hadir' => $query->where('registrations.status', Registration::AKTIF),
                'dibatalkan' => $query->where('registrations.status', Registration::DIBATALKAN),
                default => null,
            };
        }

        if (! empty($filters['session_id'])) {
            $query->where('registrations.session_id', $filters['session_id']);
        }

        if (! empty($filters['study_program_id'])) {
            if ($filters['study_program_id'] === 'umum') {
                $query->where('registrations.participant_type', Registration::TYPE_UMUM);
            } else {
                $query->where('registrations.study_program_id', $filters['study_program_id']);
            }
        }

        if (! empty($filters['q'])) {
            $q = trim($filters['q']);
            $query->where(function (Builder $w) use ($q) {
                $w->where('users.name', 'like', "%{$q}%")
                    ->orWhere('users.email', 'like', "%{$q}%")
                    ->orWhere('users.phone', 'like', "%{$q}%")
                    ->orWhere('registrations.nim', 'like', "%{$q}%");

                // Pencarian ID tiket (angka persis), contoh: 23 atau #23
                $digits = ltrim($q, '#');
                if ($digits !== '' && ctype_digit($digits)) {
                    $w->orWhere('registrations.ticket_no', (int) $digits);
                }
            });
        }

        return $query->orderBy('registrations.ticket_no');
    }
}

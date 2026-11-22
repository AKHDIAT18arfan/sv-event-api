<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Registration;
use Illuminate\Support\Collection;

/** Hitungan untuk kartu "Hadir 38/100" dan rekap per sesi dan per program studi. */
class EventStats
{
    /** Terdaftar dan hadir per sesi. */
    public function perSession(Event $event): Collection
    {
        return $event->sessions()
            ->orderBy('start_at')
            ->withCount([
                'registrations as registered' => fn ($q) => $q->whereIn('status', Registration::ACTIVE_STATUSES),
                'registrations as attended' => fn ($q) => $q->where('status', Registration::HADIR),
            ])
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'quota_total' => $s->quota_total,
                'quota_remaining' => $s->quota_remaining,
                'registered' => (int) $s->registered,
                'attended' => (int) $s->attended,
            ]);
    }

    /** Rekap per program studi (JOIN beberapa tabel). Peserta Umum masuk kelompok "Umum". */
    public function byProgram(Event $event): Collection
    {
        return Registration::query()
            ->join('users', 'users.id', '=', 'registrations.user_id')
            ->join('event_sessions', 'event_sessions.id', '=', 'registrations.session_id')
            ->leftJoin('study_programs', 'study_programs.id', '=', 'registrations.study_program_id')
            ->where('registrations.event_id', $event->id)
            ->whereIn('registrations.status', Registration::ACTIVE_STATUSES)
            ->groupBy('study_programs.id', 'study_programs.name')
            ->selectRaw("COALESCE(study_programs.name, 'Umum') as program, COUNT(*) as registered, SUM(CASE WHEN registrations.status = 'hadir' THEN 1 ELSE 0 END) as attended")
            ->orderByDesc('registered')
            ->get()
            ->map(fn ($row) => [
                'program' => $row->program,
                'registered' => (int) $row->registered,
                'attended' => (int) $row->attended,
            ]);
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegistrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canCancel = $this->status === Registration::AKTIF
            && $this->relationLoaded('session')
            && ! $this->session->hasStarted();

        return [
            'id' => $this->id,
            'ticket_no' => $this->ticket_no,
            'ticket_label' => '#'.$this->ticket_no,
            'status' => $this->status,
            'can_cancel' => $canCancel,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'registered_at' => $this->created_at?->toIso8601String(),
            'checked_at' => $this->whenLoaded('attendance', fn () => $this->attendance?->checked_at?->toIso8601String()),
            'participant_type' => $this->participant_type,
            'nim' => $this->nim,
            'event' => $this->whenLoaded('event', fn () => [
                'id' => $this->event->id,
                'name' => $this->event->name,
                'venue' => $this->event->venue,
                'organizer' => $this->event->organizer_name,
            ]),
            'session' => $this->whenLoaded('session', fn () => [
                'id' => $this->session->id,
                'name' => $this->session->name,
                'start_at' => $this->session->start_at?->toIso8601String(),
                'end_at' => $this->session->end_at?->toIso8601String(),
            ]),
            // Data peserta: nama dan kontak dari akun; NIM dan program studi dari data pendaftaran
            'participant' => $this->whenLoaded('user', fn () => [
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
                'type' => $this->participant_type,
                'nim' => $this->nim,
                'study_program' => $this->participant_type === Registration::TYPE_UMUM
                    ? 'Umum'
                    : ($this->relationLoaded('studyProgram') ? $this->studyProgram?->name : null),
                // Kolom "NIM / Identitas" di tabel presensi: NIM untuk Mahasiswa, nomor telepon untuk Umum
                'identity' => $this->participant_type === Registration::TYPE_MAHASISWA ? $this->nim : $this->user->phone,
            ]),
        ];
    }
}

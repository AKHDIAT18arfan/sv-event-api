<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'venue' => $this->venue,
            'poster_url' => $this->poster_path ? Storage::disk('public')->url($this->poster_path) : null,
            'target_peserta' => $this->target_peserta,
            'organizer' => $this->organizer_name,
            'registration_open_at' => $this->registration_open_at?->toIso8601String(),
            'registration_close_at' => $this->registration_close_at?->toIso8601String(),
            'status' => $this->status,
            // Ringkasan untuk kartu (diisi saat query memakai withSum/withMin/withMax/withCount)
            'quota_total' => $this->when(isset($this->sessions_sum_quota_total), fn () => (int) $this->sessions_sum_quota_total),
            'quota_remaining' => $this->when(isset($this->sessions_sum_quota_remaining), fn () => (int) $this->sessions_sum_quota_remaining),
            'starts_at' => $this->when(isset($this->sessions_min_start_at), fn () => $this->sessions_min_start_at),
            'ends_at' => $this->when(isset($this->sessions_max_end_at), fn () => $this->sessions_max_end_at),
            'registrations_count' => $this->whenCounted('registrations'),
            'owner' => $this->whenLoaded('owner', fn () => $this->owner ? ['id' => $this->owner->id, 'name' => $this->owner->name] : null),
            'sessions' => SessionResource::collection($this->whenLoaded('sessions')),
        ];
    }
}

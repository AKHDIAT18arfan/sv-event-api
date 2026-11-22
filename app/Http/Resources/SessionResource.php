<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'start_at' => $this->start_at?->toIso8601String(),
            'end_at' => $this->end_at?->toIso8601String(),
            'quota_total' => $this->quota_total,
            'quota_remaining' => $this->quota_remaining,
            'is_full' => $this->quota_remaining < 1,
            // Sesi yang sudah dimulai otomatis Ditutup. Tombol daftar aktif bila event Dibuka
            // (status dan jadwal) dan is_open bernilai true.
            'has_started' => $this->hasStarted(),
            'is_open' => ! $this->hasStarted() && $this->quota_remaining > 0,
        ];
    }
}

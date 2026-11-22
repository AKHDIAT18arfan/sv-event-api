<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            // Diisi saat query memakai withCount (halaman Kelola Akun Panitia)
            'open_events_count' => $this->when(isset($this->open_events_count), fn () => (int) $this->open_events_count),
            'active_tickets_count' => $this->when(isset($this->active_tickets_count), fn () => (int) $this->active_tickets_count),
        ];
    }
}

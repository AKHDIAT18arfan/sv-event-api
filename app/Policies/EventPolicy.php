<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * Aturan bisnis 6: panitia hanya mengakses event miliknya (yang dibuat akunnya).
     * SuperAdmin (BEM SV) sengaja TIDAK punya akses ke presensi dan pengelolaan event.
     */
    public function manage(User $user, Event $event): bool
    {
        return $user->role === User::ROLE_PANITIA
            && (int) $event->created_by === (int) $user->id;
    }
}

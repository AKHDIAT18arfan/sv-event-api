<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\EventStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Rekap per event (hanya panitia pemilik). Tidak ada halaman rekap di desain saat ini,
 * endpoint ini disiapkan bila dosen meminta rekap per sesi dan per program studi.
 */
class DashboardController extends Controller
{
    use ApiResponse;

    public function __construct(private EventStats $stats)
    {
    }

    public function event(Event $event): JsonResponse
    {
        Gate::authorize('manage', $event);

        $sessions = $this->stats->perSession($event);
        $registered = (int) $sessions->sum('registered');
        $attended = (int) $sessions->sum('attended');

        return $this->ok([
            'event' => ['id' => $event->id, 'name' => $event->name, 'status' => $event->status],
            'totals' => [
                'registered' => $registered,
                'attended' => $attended,
                'not_attended' => $registered - $attended,
                'quota_total' => (int) $sessions->sum('quota_total'),
                'quota_remaining' => (int) $sessions->sum('quota_remaining'),
            ],
            'per_session' => $sessions->values(),
            'by_program' => $this->stats->byProgram($event)->values(),
        ]);
    }
}

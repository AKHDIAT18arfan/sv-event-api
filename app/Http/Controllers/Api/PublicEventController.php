<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicEventController extends Controller
{
    use ApiResponse;

    /**
     * Katalog event publik (event yang dihapus tidak tampil karena soft delete).
     * Filter: q (nama/lokasi), organizer (teks, contoh "Teknik Informatika" atau "HIMADEPSI"),
     * target_peserta, date (tanggal sesi), status, paginasi.
     */
    public function index(Request $request)
    {
        $events = Event::query()
            ->withSum('sessions', 'quota_total')
            ->withSum('sessions', 'quota_remaining')
            ->withMin('sessions', 'start_at')
            ->withMax('sessions', 'end_at')
            ->when($request->query('q'), function ($query, $q) {
                $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('venue', 'like', "%{$q}%"));
            })
            ->when($request->query('organizer'), fn ($query, $v) => $query->where('organizer_name', 'like', "%{$v}%"))
            ->when($request->query('target_peserta'), fn ($query, $v) => $query->where('target_peserta', $v))
            ->when($request->query('status'), fn ($query, $v) => $query->where('status', $v))
            ->when($request->query('date'), function ($query, $date) {
                $query->whereHas('sessions', fn ($s) => $s->whereDate('start_at', $date));
            })
            ->orderByRaw("CASE status WHEN 'dibuka' THEN 0 ELSE 1 END")
            ->orderByDesc('registration_open_at')
            ->paginate(min((int) $request->query('per_page', 9), 50));

        return EventResource::collection($events)->additional(['success' => true]);
    }

    /** Detail event beserta sesi dan sisa kuota. */
    public function show(Event $event): JsonResponse
    {
        $event->load(['sessions' => fn ($q) => $q->orderBy('start_at')]);

        return $this->ok(new EventResource($event));
    }
}

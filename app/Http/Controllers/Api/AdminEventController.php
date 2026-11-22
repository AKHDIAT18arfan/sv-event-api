<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Kelola Event Kampus (SuperAdmin / BEM SV): hanya melihat dan menghapus.
 * SuperAdmin tidak membuat, mengubah, atau mengubah status event; itu tugas akun panitia.
 */
class AdminEventController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $events = Event::query()
            ->with('owner')
            ->withCount(['registrations as registrations_count' => fn ($q) => $q->whereIn('status', Registration::ACTIVE_STATUSES)])
            ->withMin('sessions', 'start_at')
            ->withMax('sessions', 'end_at')
            ->when($request->query('q'), function ($query, $q) {
                $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('organizer_name', 'like', "%{$q}%"));
            })
            ->when($request->query('status'), fn ($query, $v) => $query->where('status', $v))
            ->latest('id')
            ->paginate(min((int) $request->query('per_page', 5), 50));

        return EventResource::collection($events)->additional([
            'success' => true,
            // Kartu "Pendaftaran Dibuka" dan jumlah per tab
            'summary' => [
                'total' => Event::count(),
                'dibuka' => Event::where('status', 'dibuka')->count(),
                'ditutup' => Event::where('status', 'ditutup')->count(),
            ],
        ]);
    }

    /**
     * Hapus event. Event disembunyikan (soft delete): hilang dari katalog dan semua daftar,
     * tetapi data pendaftar tetap tersimpan. Alasan wajib diisi dan tercatat bersama siapa yang menghapus.
     */
    public function destroy(DeleteEventRequest $request, Event $event): JsonResponse
    {
        $event->forceFill([
            'deleted_reason' => $request->validated()['reason'],
            'deleted_by' => $request->user()->id,
        ])->save();

        $event->delete();

        return $this->ok(null);
    }
}

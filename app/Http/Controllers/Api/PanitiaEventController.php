<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Event milik akun panitia: buat, ubah, ubah status, hapus.
 * Event yang dipublikasikan langsung berstatus Dibuka (tidak ada Draft).
 */
class PanitiaEventController extends Controller
{
    use ApiResponse;

    /** Halaman "Event Program Studi ...": daftar event milik akun ini, tab Semua/Dibuka/Ditutup. */
    public function index(Request $request)
    {
        $events = Event::query()
            ->where('created_by', $request->user()->id)
            ->withCount(['registrations as registrations_count' => fn ($q) => $q->whereIn('status', Registration::ACTIVE_STATUSES)])
            ->withSum('sessions', 'quota_total')
            ->withSum('sessions', 'quota_remaining')
            ->withMin('sessions', 'start_at')
            ->withMax('sessions', 'end_at')
            ->when($request->query('status'), fn ($query, $v) => $query->where('status', $v))
            ->latest('id')
            ->paginate(min((int) $request->query('per_page', 12), 50));

        return EventResource::collection($events)->additional(['success' => true]);
    }

    public function show(Event $event): JsonResponse
    {
        Gate::authorize('manage', $event);

        $event->load(['sessions' => fn ($q) => $q->orderBy('start_at')]);

        return $this->ok(new EventResource($event));
    }

    /** "Publikasikan Event Sekarang": event langsung berstatus Dibuka. */
    public function store(EventRequest $request): JsonResponse
    {
        $data = $request->validated();

        $event = DB::transaction(function () use ($request, $data) {
            $event = Event::create([
                ...Arr::except($data, ['poster', 'sessions']),
                'status' => 'dibuka',
                'created_by' => $request->user()->id,
                'poster_path' => $request->hasFile('poster')
                    ? $request->file('poster')->store('posters', 'public')
                    : null,
            ]);

            foreach ($data['sessions'] as $session) {
                $event->sessions()->create([
                    'name' => $session['name'],
                    'start_at' => $session['start_at'],
                    'end_at' => $session['end_at'],
                    'quota_total' => $session['quota_total'],
                    'quota_remaining' => $session['quota_total'],
                ]);
            }

            return $event;
        });

        $event->load(['sessions' => fn ($q) => $q->orderBy('start_at')]);

        return $this->ok(new EventResource($event), 201);
    }

    /**
     * Ubah event dan sinkronkan sesi.
     * Sesi dengan "id" diperbarui, tanpa "id" dibuat baru, yang tidak dikirim dihapus
     * (hanya jika belum punya pendaftar). Kuota baru tidak boleh di bawah jumlah tiket aktif.
     *
     * Catatan klien: kirim sebagai POST multipart dengan field _method=PUT bila ada upload poster.
     */
    public function update(EventRequest $request, Event $event): JsonResponse
    {
        Gate::authorize('manage', $event);

        $data = $request->validated();

        DB::transaction(function () use ($request, $event, $data) {
            $fields = Arr::except($data, ['poster', 'sessions']);

            if ($request->hasFile('poster')) {
                if ($event->poster_path) {
                    Storage::disk('public')->delete($event->poster_path);
                }
                $fields['poster_path'] = $request->file('poster')->store('posters', 'public');
            }

            $event->update($fields);

            $existing = $event->sessions()->get()->keyBy('id');
            $keepIds = [];

            foreach ($data['sessions'] as $input) {
                if (! empty($input['id'])) {
                    $session = $existing->get((int) $input['id'])
                        ?? throw new ApiException('NOT_FOUND', 'Sesi tidak ditemukan di event ini.', 404);

                    $used = $session->quota_total - $session->quota_remaining; // tiket aktif saat ini
                    if ($input['quota_total'] < $used) {
                        throw new ApiException(
                            'VALIDATION_ERROR',
                            "Kuota sesi '{$session->name}' tidak boleh di bawah jumlah tiket aktif ({$used}).",
                            422
                        );
                    }

                    $session->update([
                        'name' => $input['name'],
                        'start_at' => $input['start_at'],
                        'end_at' => $input['end_at'],
                        'quota_total' => $input['quota_total'],
                        'quota_remaining' => $input['quota_total'] - $used,
                    ]);
                    $keepIds[] = $session->id;
                } else {
                    $new = $event->sessions()->create([
                        'name' => $input['name'],
                        'start_at' => $input['start_at'],
                        'end_at' => $input['end_at'],
                        'quota_total' => $input['quota_total'],
                        'quota_remaining' => $input['quota_total'],
                    ]);
                    $keepIds[] = $new->id;
                }
            }

            foreach ($existing as $session) {
                if (in_array($session->id, $keepIds, true)) {
                    continue;
                }
                if ($session->registrations()->exists()) {
                    throw new ApiException('VALIDATION_ERROR', "Sesi '{$session->name}' sudah punya pendaftar dan tidak bisa dihapus.", 422);
                }
                $session->delete();
            }
        });

        $event->refresh()->load(['sessions' => fn ($q) => $q->orderBy('start_at')]);

        return $this->ok(new EventResource($event));
    }

    /** Modal "Ubah Status Event": Dibuka atau Ditutup (Selesai = Ditutup). */
    public function updateStatus(Request $request, Event $event): JsonResponse
    {
        Gate::authorize('manage', $event);

        $request->validate([
            'status' => ['required', Rule::in(Event::STATUSES)],
        ]);

        if ($request->input('status') === $event->status) {
            throw new ApiException('INVALID_STATUS_TRANSITION', "Event sudah berstatus {$event->status}.", 422);
        }

        $event->update(['status' => $request->input('status')]);

        return $this->ok(new EventResource($event));
    }

    /**
     * Hapus event milik sendiri. Event disembunyikan (soft delete): hilang dari katalog,
     * Tiket Saya, dan semua daftar, tetapi datanya tetap tersimpan di database.
     */
    public function destroy(Request $request, Event $event): JsonResponse
    {
        Gate::authorize('manage', $event);

        $event->forceFill(['deleted_by' => $request->user()->id])->save();
        $event->delete();

        return $this->ok(null);
    }
}

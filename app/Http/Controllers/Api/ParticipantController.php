<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RegistrationResource;
use App\Models\Event;
use App\Models\Registration;
use App\Services\EventStats;
use App\Services\ParticipantFilter;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Halaman Presensi (hanya akun panitia pemilik event): daftar peserta, centang hadir,
 * dan data untuk "Cetak Lembar Presensi" (PDF dibuat di Front-End lewat tampilan cetak).
 */
class ParticipantController extends Controller
{
    use ApiResponse;

    private const FILTER_KEYS = ['q', 'session_id', 'study_program_id', 'status', 'include_cancelled'];

    private const RELATIONS = ['session', 'user', 'studyProgram', 'attendance'];

    public function __construct(
        private TicketService $tickets,
        private ParticipantFilter $filter,
        private EventStats $stats,
    ) {
    }

    /**
     * Tabel presensi: search (nama, email, telepon, NIM, ID tiket), filter sesi,
     * program studi (nilai "umum" untuk peserta Umum), status (hadir | belum_hadir), paginasi.
     * Respons juga memuat ringkasan per sesi untuk kartu "Tingkat Kehadiran 38/100".
     */
    public function index(Request $request, Event $event)
    {
        Gate::authorize('manage', $event);

        $paginator = $this->filter
            ->query($event, $request->only(self::FILTER_KEYS))
            ->paginate(min((int) $request->query('per_page', 20), 100));

        $perSession = $this->stats->perSession($event);

        return RegistrationResource::collection($paginator)->additional([
            'success' => true,
            'summary' => [
                'total_active' => (int) $perSession->sum('registered'),
                'hadir' => (int) $perSession->sum('attended'),
                'per_session' => $perSession->values(),
            ],
        ]);
    }

    /** Centang hadir. */
    public function mark(Request $request, Event $event, Registration $registration): JsonResponse
    {
        Gate::authorize('manage', $event);

        $updated = $this->tickets->markPresent($event, $registration, $request->user());

        return $this->ok(new RegistrationResource($updated->load(self::RELATIONS)));
    }

    /** Batalkan centang hadir (salah centang). */
    public function unmark(Request $request, Event $event, Registration $registration): JsonResponse
    {
        Gate::authorize('manage', $event);

        $updated = $this->tickets->unmarkPresent($event, $registration);

        return $this->ok(new RegistrationResource($updated->load(self::RELATIONS)));
    }

    /**
     * Data lengkap tanpa paginasi untuk "Cetak Lembar Presensi".
     * Front-End menampilkannya sebagai tabel rapi (CSS @media print) lalu pengguna
     * memilih "Simpan sebagai PDF" atau mencetaknya. Maksimal 3000 baris.
     */
    public function printSheet(Request $request, Event $event): JsonResponse
    {
        Gate::authorize('manage', $event);

        $rows = $this->filter
            ->query($event, $request->only(self::FILTER_KEYS))
            ->limit(3000)
            ->get()
            ->map(fn (Registration $r) => [
                'ticket_no' => $r->ticket_no,
                'ticket_label' => '#'.$r->ticket_no,
                'name' => $r->user->name,
                'identity' => $r->participant_type === Registration::TYPE_MAHASISWA ? $r->nim : $r->user->phone,
                'study_program' => $r->participant_type === Registration::TYPE_UMUM ? 'Umum' : $r->studyProgram?->name,
                'session' => $r->session->name,
                'status' => $r->status,
                'checked_at' => $r->attendance?->checked_at?->toIso8601String(),
            ]);

        return $this->ok([
            'event' => ['id' => $event->id, 'name' => $event->name, 'organizer' => $event->organizer_name],
            'generated_at' => now()->toIso8601String(),
            'total' => $rows->count(),
            'rows' => $rows->values(),
        ]);
    }
}

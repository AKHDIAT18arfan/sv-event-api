<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClaimRequest;
use App\Http\Resources\RegistrationResource;
use App\Models\EventSession;
use App\Models\Registration;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    use ApiResponse;

    private const RELATIONS = ['event', 'session', 'user', 'studyProgram', 'attendance'];

    public function __construct(private TicketService $tickets)
    {
    }

    /**
     * Konfirmasi pendaftaran event untuk satu sesi.
     * Body: participant_type (UMUM|MAHASISWA); untuk MAHASISWA juga nim dan study_program_id.
     */
    public function store(ClaimRequest $request, EventSession $session): JsonResponse
    {
        $registration = $this->tickets->claim($request->user(), $session, $request->validated());

        return (new RegistrationResource($registration->load(self::RELATIONS)))
            ->additional(['success' => true])
            ->response()
            ->setStatusCode(201);
    }

    /** Tiket Saya. Tab: aktif | selesai. Tiket yang dibatalkan tidak ditampilkan. */
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'aktif');

        $query = Registration::query()
            ->where('user_id', $request->user()->id)
            ->whereHas('event') // event yang dihapus ikut hilang dari Tiket Saya
            ->with(self::RELATIONS);

        match ($tab) {
            'aktif' => $query->where('status', Registration::AKTIF)
                ->whereHas('session', fn ($s) => $s->where('end_at', '>=', now())),
            'selesai' => $query->where(function ($w) {
                $w->where('status', Registration::HADIR)
                    ->orWhere(function ($x) {
                        $x->where('status', Registration::AKTIF)
                            ->whereHas('session', fn ($s) => $s->where('end_at', '<', now()));
                    });
            }),
            default => throw new ApiException('VALIDATION_ERROR', 'Tab harus aktif atau selesai.', 422),
        };

        $registrations = $query->latest('id')->paginate(10);

        return RegistrationResource::collection($registrations)->additional(['success' => true]);
    }

    /** Detail E-Pass (ID tiket) milik sendiri. */
    public function show(Request $request, Registration $registration): JsonResponse
    {
        if ((int) $registration->user_id !== (int) $request->user()->id) {
            throw new ApiException('FORBIDDEN', 'Tiket ini bukan milikmu.', 403);
        }

        return $this->ok(new RegistrationResource($registration->load(self::RELATIONS)));
    }

    /** Batalkan tiket (hanya sebelum event dimulai dan belum hadir). */
    public function cancel(Request $request, Registration $registration): JsonResponse
    {
        $cancelled = $this->tickets->cancel($request->user(), $registration);

        return $this->ok(new RegistrationResource($cancelled->load(self::RELATIONS)));
    }
}

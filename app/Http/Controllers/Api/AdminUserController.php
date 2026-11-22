<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Registration;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Exceptions\ApiException;

/** Akun User (SuperAdmin): mencari dan menghapus akun peserta. Pengguna membuat akun sendiri dari beranda. */
class AdminUserController extends Controller
{
    use ApiResponse;

    public function __construct(private TicketService $tickets)
    {
    }

    /** Pencarian lewat nama atau email (NIM tidak ada di akun). */
    public function index(Request $request)
    {
        $users = User::query()
            ->where('role', User::ROLE_PESERTA)
            ->when($request->query('q'), function ($query, $q) {
                $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"));
            })
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 8), 50));

        return UserResource::collection($users)->additional(['success' => true]);
    }

    /** Data untuk modal "Hapus Akun User": daftar tiket aktif yang akan ikut dibatalkan. */
    public function show(User $user): JsonResponse
    {
        $this->ensureParticipant($user);

        $affected = $this->upcomingActiveTickets($user)->map(fn (Registration $r) => [
            'ticket_no' => $r->ticket_no,
            'event' => $r->event->name,
            'session' => $r->session->name,
            'start_at' => $r->session->start_at?->toIso8601String(),
        ])->values();

        return $this->ok([
            'user' => new UserResource($user),
            'affected_tickets' => $affected,
        ]);
    }

    /**
     * Hapus akun user = nonaktifkan (soft delete): tidak bisa login lagi, riwayat tiket tetap ada.
     * Tiket aktif yang sesinya belum dimulai dibatalkan otomatis dan kuotanya dikembalikan.
     */
    public function destroy(User $user): JsonResponse
    {
        $this->ensureParticipant($user);

        $cancelled = 0;
        foreach ($this->upcomingActiveTickets($user) as $registration) {
            if ($this->tickets->cancelBySystem($registration)) {
                $cancelled++;
            }
        }

        $user->tokens()->delete();
        // Bebaskan alamat email agar bisa dipakai mendaftar ulang
        $user->forceFill(['email' => 'deleted-'.$user->id.'-'.$user->email, 'is_active' => false])->save();
        $user->delete();

        return $this->ok(['cancelled_tickets' => $cancelled]);
    }

    private function ensureParticipant(User $user): void
    {
        if ($user->role !== User::ROLE_PESERTA) {
            throw new ApiException('NOT_FOUND', 'Akun user tidak ditemukan.', 404);
        }
    }

    private function upcomingActiveTickets(User $user)
    {
        return Registration::query()
            ->where('user_id', $user->id)
            ->where('status', Registration::AKTIF)
            ->whereHas('session', fn ($s) => $s->where('start_at', '>', now()))
            ->with(['event', 'session'])
            ->get();
    }
}

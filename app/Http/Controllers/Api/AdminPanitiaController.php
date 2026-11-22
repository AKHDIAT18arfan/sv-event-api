<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePanitiaRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Kelola Akun Panitia (SuperAdmin): satu akun per penyelenggara (prodi atau himpunan). */
class AdminPanitiaController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $users = User::query()
            ->where('role', User::ROLE_PANITIA)
            ->withCount(['ownedEvents as open_events_count' => fn ($q) => $q->where('status', 'dibuka')])
            ->when($request->query('q'), function ($query, $q) {
                $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"));
            })
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 5), 50));

        return UserResource::collection($users)->additional(['success' => true]);
    }

    public function store(StorePanitiaRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => User::ROLE_PANITIA,
            'is_active' => true,
        ]);

        return $this->ok(new UserResource($user), 201);
    }

    /**
     * Hapus akun panitia: akun tidak bisa login lagi, event yang sudah dibuat tetap tersimpan.
     * Ditolak bila akun masih punya event berstatus Dibuka, supaya event tidak kehilangan pengelolanya.
     */
    public function destroy(User $user): JsonResponse
    {
        if ($user->role !== User::ROLE_PANITIA) {
            throw new ApiException('NOT_FOUND', 'Akun panitia tidak ditemukan.', 404);
        }

        $open = $user->ownedEvents()->where('status', 'dibuka')->count();
        if ($open > 0) {
            throw new ApiException(
                'HAS_ACTIVE_EVENTS',
                "Akun ini masih punya {$open} event berstatus Dibuka. Tutup atau selesaikan event tersebut terlebih dahulu.",
                422
            );
        }

        $user->tokens()->delete();
        // Bebaskan alamat email agar bisa dipakai membuat akun baru; riwayat tetap terhubung lewat id
        $user->forceFill(['email' => 'deleted-'.$user->id.'-'.$user->email, 'is_active' => false])->save();
        $user->delete();

        return $this->ok(null);
    }
}

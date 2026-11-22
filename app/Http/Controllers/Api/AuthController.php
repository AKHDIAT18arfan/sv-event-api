<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    use ApiResponse;

    /** Buat akun peserta. Sama untuk semua orang; Umum/Mahasiswa dipilih saat mendaftar event. */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'], // di-hash otomatis oleh cast 'hashed' di model User
            'role' => User::ROLE_PESERTA,
            'is_active' => true,
        ]);

        return $this->ok([
            'token' => $user->createToken('web')->plainTextToken,
            'user' => new UserResource($user),
        ], 201);
    }

    /** Satu login untuk semua peran (email dan kata sandi). */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->input('email'))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            throw new ApiException('INVALID_CREDENTIALS', 'Email atau kata sandi salah.', 401);
        }

        if (! $user->is_active) {
            throw new ApiException('FORBIDDEN', 'Akun ini dinonaktifkan. Hubungi admin BEM SV.', 403);
        }

        return $this->ok([
            'token' => $user->createToken('web')->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Data akun yang sedang login. Untuk peserta, ikut dikirim NIM dan prodi dari pendaftaran
     * Mahasiswa terakhir supaya form konfirmasi pendaftaran bisa terisi otomatis.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $defaults = null;
        if ($user->role === User::ROLE_PESERTA) {
            $last = Registration::query()
                ->where('user_id', $user->id)
                ->where('participant_type', Registration::TYPE_MAHASISWA)
                ->latest('id')
                ->first();

            $defaults = $last ? ['nim' => $last->nim, 'study_program_id' => $last->study_program_id] : null;
        }

        return $this->ok([
            'user' => new UserResource($user),
            'registration_defaults' => $defaults,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return $this->ok(null);
    }
}

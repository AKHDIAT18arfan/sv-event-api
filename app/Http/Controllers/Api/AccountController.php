<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

/** Halaman "Pengaturan Akun Saya": nama, nomor telepon, dan kata sandi. */
class AccountController extends Controller
{
    use ApiResponse;

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return $this->ok(new UserResource($user->fresh()));
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->input('current_password'), $user->password)) {
            throw new ApiException('INVALID_CREDENTIALS', 'Kata sandi lama salah.', 422);
        }

        $user->update(['password' => $request->input('password')]);

        // Keluarkan perangkat lain, pertahankan sesi yang sedang dipakai
        $current = $user->currentAccessToken();
        if ($current instanceof PersonalAccessToken) {
            $user->tokens()->where('id', '!=', $current->id)->delete();
        }

        return $this->ok(null);
    }
}

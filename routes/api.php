<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AdminEventController;
use App\Http\Controllers\Api\AdminPanitiaController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PanitiaEventController;
use App\Http\Controllers\Api\ParticipantController;
use App\Http\Controllers\Api\PublicEventController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\StudyProgramController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API SV Event  (prefix /api, token Bearer dari Laravel Sanctum)
|--------------------------------------------------------------------------
| Peran:
|   peserta     : membuat akun sendiri, mendaftar event (Umum/Mahasiswa dipilih saat daftar event)
|   panitia     : akun penyelenggara; membuat dan mengelola event miliknya, presensi
|   super_admin : BEM SV; kelola akun panitia, hapus event, hapus akun user
*/

// ---------- Publik ----------
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::get('study-programs', [StudyProgramController::class, 'index']);
Route::get('events', [PublicEventController::class, 'index']);
Route::get('events/{event}', [PublicEventController::class, 'show']);

// ---------- Harus login ----------
Route::middleware('auth:sanctum')->group(function () {

    // Pengaturan Akun Saya (semua peran)
    Route::put('account/profile', [AccountController::class, 'updateProfile']);
    Route::put('account/password', [AccountController::class, 'changePassword']);

    // Peserta
    Route::middleware('role:peserta')->group(function () {
        Route::post('sessions/{session}/registrations', [RegistrationController::class, 'store']);
        Route::get('my/registrations', [RegistrationController::class, 'index']);
        Route::get('registrations/{registration}', [RegistrationController::class, 'show']);
        Route::post('registrations/{registration}/cancel', [RegistrationController::class, 'cancel']);
    });

    // Panitia (akses per event diperiksa oleh EventPolicy: hanya event yang dibuat akunnya)
    Route::middleware('role:panitia')->group(function () {
        Route::prefix('panitia')->group(function () {
            Route::get('events', [PanitiaEventController::class, 'index']);
            Route::post('events', [PanitiaEventController::class, 'store']);
            Route::get('events/{event}', [PanitiaEventController::class, 'show']);
            Route::put('events/{event}', [PanitiaEventController::class, 'update']);
            Route::patch('events/{event}/status', [PanitiaEventController::class, 'updateStatus']);
            Route::delete('events/{event}', [PanitiaEventController::class, 'destroy']);
        });

        Route::get('events/{event}/participants', [ParticipantController::class, 'index']);
        Route::get('events/{event}/participants/print', [ParticipantController::class, 'printSheet']);
        Route::post('events/{event}/participants/{registration}/attendance', [ParticipantController::class, 'mark']);
        Route::delete('events/{event}/participants/{registration}/attendance', [ParticipantController::class, 'unmark']);
        Route::get('events/{event}/dashboard', [DashboardController::class, 'event']);
    });

    // SuperAdmin (BEM SV)
    Route::prefix('admin')->middleware('role:super_admin')->group(function () {
        Route::get('events', [AdminEventController::class, 'index']);
        Route::delete('events/{event}', [AdminEventController::class, 'destroy']);

        Route::get('panitia', [AdminPanitiaController::class, 'index']);
        Route::post('panitia', [AdminPanitiaController::class, 'store']);
        Route::delete('panitia/{user}', [AdminPanitiaController::class, 'destroy']);

        Route::get('users', [AdminUserController::class, 'index']);
        Route::get('users/{user}', [AdminUserController::class, 'show']);
        Route::delete('users/{user}', [AdminUserController::class, 'destroy']);
    });
});

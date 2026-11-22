<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Membuat dan mengubah event beserta sesinya (oleh akun panitia).
 * Dikirim sebagai multipart/form-data karena ada upload poster.
 */
class EventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'organizer_name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'venue' => ['required', 'string', 'max:200'],
            // Wajib saat membuat event; saat mengubah boleh kosong (poster lama tetap dipakai)
            'poster' => [$this->route('event') ? 'nullable' : 'required', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'target_peserta' => ['required', Rule::in(['MAHASISWA', 'SEMUA'])],
            'registration_open_at' => ['required', 'date'],
            'registration_close_at' => ['required', 'date', 'after:registration_open_at'],

            'sessions' => ['required', 'array', 'min:1'],
            'sessions.*.id' => ['nullable', 'integer'],
            'sessions.*.name' => ['required', 'string', 'max:150'],
            'sessions.*.start_at' => ['required', 'date'],
            'sessions.*.end_at' => ['required', 'date', 'after:sessions.*.start_at'],
            'sessions.*.quota_total' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama event wajib diisi.',
            'organizer_name.required' => 'Ormawa / himpunan penyelenggara wajib diisi.',
            'venue.required' => 'Lokasi pelaksanaan wajib diisi.',
            'name.max' => 'Nama event maksimal 120 karakter.',
            'poster.required' => 'Poster banner wajib diunggah.',
            'poster.mimes' => 'Poster harus berformat PNG, JPG, atau WEBP.',
            'poster.uploaded' => 'Poster gagal diunggah. Pastikan ukurannya maksimal 5 MB.',
            'poster.max' => 'Ukuran poster maksimal 5 MB.',
            'target_peserta.required' => 'Pilih kategori peserta.',
            'registration_open_at.required' => 'Tanggal buka pendaftaran wajib diisi.',
            'registration_close_at.required' => 'Tanggal tutup pendaftaran wajib diisi.',
            'registration_close_at.after' => 'Tanggal & waktu tutup harus setelah tanggal buka.',
            'sessions.required' => 'Tambahkan minimal satu sesi.',
            'sessions.min' => 'Tambahkan minimal satu sesi.',
            'sessions.*.name.required' => 'Nama sesi wajib diisi.',
            'sessions.*.start_at.required' => 'Jam mulai sesi wajib diisi.',
            'sessions.*.end_at.required' => 'Jam selesai sesi wajib diisi.',
            'sessions.*.end_at.after' => 'Jam selesai harus setelah jam mulai.',
            'sessions.*.quota_total.required' => 'Kuota sesi wajib diisi.',
            'sessions.*.quota_total.min' => 'Kuota harus lebih besar dari 0.',
        ];
    }
}

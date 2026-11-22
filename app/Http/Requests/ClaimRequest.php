<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Konfirmasi pendaftaran event. Jenis peserta dipilih di sini, bukan di akun:
 *  - UMUM      : tidak ada isian tambahan (nama dan telepon diambil dari akun)
 *  - MAHASISWA : wajib mengisi NIM dan program studi
 */
class ClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'participant_type' => ['required', Rule::in(['MAHASISWA', 'UMUM'])],
            'nim' => ['required_if:participant_type,MAHASISWA', 'nullable', 'string', 'max:30'],
            'study_program_id' => ['required_if:participant_type,MAHASISWA', 'nullable', 'exists:study_programs,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'participant_type.required' => 'Pilih Umum atau Mahasiswa.',
            'nim.required_if' => 'NIM wajib diisi.',
            'study_program_id.required_if' => 'Program studi wajib dipilih.',
            'study_program_id.exists' => 'Program studi tidak ditemukan.',
        ];
    }
}

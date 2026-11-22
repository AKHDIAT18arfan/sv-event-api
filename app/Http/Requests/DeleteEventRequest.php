<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** SuperAdmin wajib mengisi alasan saat menghapus event (tercatat sebagai rekam audit). */
class DeleteEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan wajib diisi.',
            'reason.min' => 'Alasan minimal 10 karakter.',
        ];
    }
}

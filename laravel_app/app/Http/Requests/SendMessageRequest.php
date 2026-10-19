<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public const MAX_LENGTH = 1000;

    public function authorize(): bool
    {
        return true;
    }

    /** FR-17: pesan tidak kosong, maksimal 1000 karakter. */
    public function rules(): array
    {
        return [
            'session_id' => ['nullable', 'integer', 'min:1'],
            'message' => ['required', 'string', 'max:'.self::MAX_LENGTH],
        ];
    }

    public function messages(): array
    {
        return [
            'session_id.integer' => 'session_id tidak valid.',
            'session_id.min' => 'session_id tidak valid.',
            'message.required' => 'Pesan tidak boleh kosong.',
            'message.string' => 'Pesan harus berupa teks.',
            'message.max' => 'Pesan maksimal :max karakter.',
        ];
    }
}

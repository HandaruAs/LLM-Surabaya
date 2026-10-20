<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public const MAX_LENGTH = 1000;

    /** Batas riwayat yang boleh dikirim tamu dari browser. */
    public const MAX_HISTORY_ITEMS = 20;

    public const MAX_HISTORY_CONTENT = 8000;

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

            // Hanya dipakai tamu: riwayat obrolan yang dipegang browser.
            // User yang login mengambil riwayat dari database, jadi field ini diabaikan.
            'history' => ['nullable', 'array', 'max:'.self::MAX_HISTORY_ITEMS],
            'history.*' => ['array:role,content'],
            'history.*.role' => ['required', 'string', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:'.self::MAX_HISTORY_CONTENT],
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
            'history.array' => 'Riwayat tidak valid.',
            'history.max' => 'Riwayat terlalu panjang.',
            'history.*.array' => 'Riwayat tidak valid.',
            'history.*.role.*' => 'Riwayat tidak valid.',
            'history.*.content.*' => 'Riwayat tidak valid.',
        ];
    }
}

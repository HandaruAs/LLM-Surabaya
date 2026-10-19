<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RenameSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Judul tidak boleh kosong.',
            'title.max' => 'Judul maksimal :max karakter.',
        ];
    }
}

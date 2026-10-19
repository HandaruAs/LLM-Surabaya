<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Pesan terakhir di sesi sudah dijawab, jadi tidak ada yang perlu dikirim ulang. */
class NothingToRetryException extends RuntimeException
{
    public function report(): void {}

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'Tidak ada pertanyaan yang perlu dikirim ulang.',
        ], 422);
    }
}

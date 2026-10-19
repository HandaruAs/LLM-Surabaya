<?php

namespace App\Services;

use App\Models\ChatSession;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

/** Layanan AI gagal setelah pesan pengguna tersimpan (FR-15). */
class ChatFailedException extends RuntimeException
{
    public function __construct(public readonly ChatSession $session, ?Throwable $previous = null)
    {
        parent::__construct('Layanan AI tidak tersedia.', 0, $previous);
    }

    /** Sudah dicatat di RagClient sebagai warning; tidak perlu stack trace. */
    public function report(): void {}

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'Maaf, layanan AI sedang tidak tersedia. Pertanyaanmu sudah tersimpan, silakan coba lagi beberapa saat lagi.',
            'session_id' => $this->session->id,
            'title' => $this->session->title,
        ], 503);
    }
}

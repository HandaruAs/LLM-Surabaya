<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\ChatService;
use App\Services\Rag\RagUnavailableException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ChatController extends Controller
{
    /**
     * POST /api/chat (FR-10, FR-11, FR-15).
     *
     * - User login  : pertanyaan & jawaban disimpan ke sesinya (riwayat).
     * - Tamu        : langsung dijawab, TIDAK ada yang disimpan. session_id selalu null.
     */
    public function store(SendMessageRequest $request, ChatService $chat): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->storeAsGuest($request, $chat);
        }

        $session = null;
        if ($sessionId = $request->validated('session_id')) {
            // Sesi milik user lain diperlakukan sama dengan tidak ada (404).
            $session = ChatSession::ownedBy($user)->findOrFail($sessionId);
        }

        [$session, $answer] = $chat->send($user, $session, $request->validated('message'));

        return $this->response($session, $answer);
    }

    /**
     * POST /api/sessions/{id}/retry — untuk tombol "Kirim ulang" setelah 503.
     * Menjawab pertanyaan terakhir yang belum terjawab tanpa menyimpannya lagi.
     * Hanya untuk user login (tamu cukup mengirim ulang pertanyaannya).
     */
    public function retry(Request $request, string $id, ChatService $chat): JsonResponse
    {
        $session = ChatSession::ownedBy($request->user())->findOrFail($id);

        // Cegah klik ganda menghasilkan dua jawaban untuk pertanyaan yang sama.
        $lock = Cache::lock("chat-retry:{$session->id}", (int) config('chat.rag.timeout') + 10);
        if (! $lock->get()) {
            return response()->json(['message' => 'Pertanyaan ini sedang diproses. Tunggu sebentar.'], 409);
        }

        try {
            [$session, $answer] = $chat->retry($session);
        } finally {
            $lock->release();
        }

        return $this->response($session, $answer);
    }

    private function storeAsGuest(SendMessageRequest $request, ChatService $chat): JsonResponse
    {
        // Tamu tidak punya sesi tersimpan, jadi session_id tidak boleh dipakai.
        if ($request->validated('session_id')) {
            return response()->json(['message' => 'Masuk terlebih dahulu untuk membuka riwayat percakapan.'], 401);
        }

        try {
            $result = $chat->askAsGuest($request->validated('message'), $request->validated('history') ?? []);
        } catch (RagUnavailableException) {
            return response()->json([
                'message' => 'Maaf, layanan AI sedang tidak tersedia. Silakan coba lagi beberapa saat lagi.',
                'session_id' => null,
            ], 503);
        }

        return response()->json([
            'session_id' => null,
            'title' => null,
            'message' => [
                'id' => null, // tidak disimpan, jadi tidak punya id
                'role' => ChatMessage::ROLE_ASSISTANT,
                'content' => $result->answer,
                'sources' => $result->filenames(),
                'created_at' => now()->toIso8601ZuluString(),
            ],
        ]);
    }

    private function response(ChatSession $session, ChatMessage $answer): JsonResponse
    {
        return response()->json([
            'session_id' => $session->id,
            'title' => $session->title,
            'message' => $answer->toApi(),
        ]);
    }
}

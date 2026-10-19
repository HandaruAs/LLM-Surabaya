<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ChatController extends Controller
{
    /** POST /api/chat (FR-10, FR-11, FR-15). */
    public function store(SendMessageRequest $request, ChatService $chat): JsonResponse
    {
        $visitorId = $request->attributes->get('visitor_id');

        $session = null;
        if ($sessionId = $request->validated('session_id')) {
            // Sesi milik pengunjung lain diperlakukan sama dengan tidak ada (404).
            $session = ChatSession::ownedBy($visitorId)->findOrFail($sessionId);
        }

        [$session, $answer] = $chat->send($visitorId, $session, $request->validated('message'));

        return $this->response($session, $answer);
    }

    /**
     * POST /api/sessions/{id}/retry — untuk tombol "Kirim ulang" setelah 503.
     * Menjawab pertanyaan terakhir yang belum terjawab tanpa menyimpannya lagi.
     */
    public function retry(Request $request, string $id, ChatService $chat): JsonResponse
    {
        $session = ChatSession::ownedBy($request->attributes->get('visitor_id'))->findOrFail($id);

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

    private function response(ChatSession $session, ChatMessage $answer): JsonResponse
    {
        return response()->json([
            'session_id' => $session->id,
            'title' => $session->title,
            'message' => $answer->toApi(),
        ]);
    }
}

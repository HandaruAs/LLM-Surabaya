<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RenameSessionRequest;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SessionController extends Controller
{
    /** GET /api/sessions — sesi milik user yang login, terbaru dulu (FR-12). */
    public function index(Request $request): JsonResponse
    {
        $sessions = ChatSession::ownedBy($request->user())
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'updated_at'])
            ->map(fn (ChatSession $s) => $this->sessionToApi($s));

        return response()->json(['data' => $sessions]);
    }

    /** GET /api/sessions/{id}/messages — urut dari yang terlama (FR-13). */
    public function messages(Request $request, int $id): JsonResponse
    {
        $session = $this->findOwned($request, $id);

        $messages = $session->messages()
            ->orderBy('id')
            ->get()
            ->map(fn (ChatMessage $m) => $m->toApi());

        return response()->json([
            'session' => $this->sessionToApi($session),
            'data' => $messages,
        ]);
    }

    /** PATCH /api/sessions/{id} — ganti nama (FR-14). */
    public function update(RenameSessionRequest $request, int $id): JsonResponse
    {
        $session = $this->findOwned($request, $id);
        $session->update(['title' => $request->validated('title')]);

        return response()->json($this->sessionToApi($session));
    }

    /** DELETE /api/sessions/{id} — hapus sesi beserta pesannya (FR-14). */
    public function destroy(Request $request, int $id): Response
    {
        $this->findOwned($request, $id)->delete(); // pesan & sumber ikut terhapus (cascade)

        return response()->noContent();
    }

    /** DELETE /api/sessions — tombol "Hapus semua": hapus seluruh sesi milik user ini. */
    public function destroyAll(Request $request): Response
    {
        ChatSession::ownedBy($request->user())->delete(); // cascade ke pesan & sumber

        return response()->noContent();
    }

    private function findOwned(Request $request, int $id): ChatSession
    {
        return ChatSession::ownedBy($request->user())->findOrFail($id);
    }

    private function sessionToApi(ChatSession $session): array
    {
        return [
            'id' => $session->id,
            'title' => $session->title ?? 'Percakapan baru',
            'updated_at' => $session->updated_at?->toIso8601ZuluString(),
        ];
    }
}

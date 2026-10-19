<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\Rag\RagClient;
use App\Services\Rag\RagUnavailableException;
use Illuminate\Support\Facades\DB;

class ChatService
{
    public function __construct(
        private readonly RagClient $rag,
        private readonly int $historyLimit,
    ) {}

    /**
     * Alur PRD bagian 10: simpan pesan pengguna, panggil RAG dengan riwayat,
     * simpan jawaban beserta sumbernya.
     *
     * Bila RAG gagal, pesan pengguna (dan sesi baru) tetap tersimpan,
     * jawaban asisten TIDAK disimpan, lalu RagUnavailableException dilempar
     * dengan sesi terlampir agar UI tetap tahu session_id-nya.
     *
     * @throws ChatFailedException
     */
    public function send(string $visitorId, ?ChatSession $session, string $message): array
    {
        [$session, $userMessage] = DB::transaction(function () use ($visitorId, $session, $message) {
            $session ??= ChatSession::create([
                'visitor_id' => $visitorId,
                'title' => ChatSession::titleFrom($message),
            ]);

            $userMessage = $session->messages()->create([
                'role' => ChatMessage::ROLE_USER,
                'content' => $message,
            ]);

            return [$session, $userMessage];
        });

        return $this->answer($session, $userMessage);
    }

    /**
     * Kirim ulang pertanyaan terakhir yang belum terjawab (mis. setelah 503),
     * TANPA menyimpan pesan pengguna lagi.
     *
     * @throws NothingToRetryException bila pesan terakhir sudah berupa jawaban
     * @throws ChatFailedException
     */
    public function retry(ChatSession $session): array
    {
        $last = $session->messages()->orderByDesc('id')->first();

        if ($last === null || $last->role !== ChatMessage::ROLE_USER) {
            throw new NothingToRetryException;
        }

        return $this->answer($session, $last);
    }

    /** Panggil RAG untuk satu pesan pengguna lalu simpan jawabannya. */
    private function answer(ChatSession $session, ChatMessage $userMessage): array
    {
        $history = $this->historyBefore($session, $userMessage);

        $startedAt = hrtime(true);
        try {
            $result = $this->rag->ask($userMessage->content, $history);
        } catch (RagUnavailableException $e) {
            throw new ChatFailedException($session, $e);
        }
        $elapsedMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        $assistantMessage = DB::transaction(function () use ($session, $result, $elapsedMs) {
            $assistant = $session->messages()->create([
                'role' => ChatMessage::ROLE_ASSISTANT,
                'content' => $result->answer,
                'sources' => $result->filenames(),
                'response_ms' => $elapsedMs,
            ]);

            $assistant->sourceChunks()->createMany($this->sourceRows($result->chunks));

            return $assistant;
        });

        return [$session->refresh(), $assistantMessage];
    }

    /**
     * Baris untuk tabel message_sources (skema tim AI):
     * - chunk_snapshot wajib, jadi sumber yang hanya berupa nama file
     *   cukup disimpan di chat_messages.sources;
     * - primary key (message_id, filename, chunk) → buang duplikat;
     * - chunk_id ber-FK ke rag_chunks → hanya diisi bila potongannya masih ada.
     */
    private function sourceRows(array $chunks): array
    {
        $chunks = array_filter($chunks, fn (array $c) => $c['content'] !== null && $c['content'] !== '');

        $ids = array_values(array_filter(array_column($chunks, 'chunk_id')));
        $existing = $ids
            ? DB::table('rag_chunks')->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all()
            : [];

        $rows = [];
        foreach ($chunks as $chunk) {
            $key = $chunk['filename']."\0".$chunk['content'];
            $rows[$key] ??= [
                'chunk_id' => in_array($chunk['chunk_id'], $existing, true) ? $chunk['chunk_id'] : null,
                'filename_snapshot' => $chunk['filename'],
                'chunk_snapshot' => $chunk['content'],
            ];
        }

        return array_values($rows);
    }

    /**
     * Maksimal N pesan terakhir SEBELUM pertanyaan sekarang, urut lama → baru
     * (FR-05). Pertanyaan sekarang dikirim terpisah di field "question".
     */
    private function historyBefore(ChatSession $session, ChatMessage $current): array
    {
        if ($this->historyLimit <= 0) {
            return [];
        }

        return $session->messages()
            ->where('id', '<', $current->id)
            ->orderByDesc('id')
            ->limit($this->historyLimit)
            ->get(['role', 'content'])
            ->reverse()
            ->map(fn (ChatMessage $m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->all();
    }
}

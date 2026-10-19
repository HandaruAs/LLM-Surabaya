<?php

namespace App\Services\Rag;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Klien HTTP ke layanan RAG internal (Python, hanya di localhost).
 * Kontrak: PRD bagian 8.2 — POST /chat {question, history} -> {answer, sources}.
 */
class RagClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout,
    ) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     *
     * @throws RagUnavailableException
     */
    public function ask(string $question, array $history = []): RagAnswer
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->acceptJson()
                ->timeout($this->timeout)
                ->connectTimeout(5)
                ->post('/chat', [
                    'question' => $question,
                    'history' => array_values($history),
                ])
                ->throw();
        } catch (ConnectionException|RequestException $e) {
            Log::warning('Layanan RAG gagal dipanggil', ['error' => $e->getMessage()]);

            throw new RagUnavailableException(previous: $e);
        }

        $answer = trim((string) $response->json('answer', ''));

        // Jangan simpan jawaban kosong (FR-15).
        if ($answer === '') {
            Log::warning('Layanan RAG mengembalikan jawaban kosong', ['body' => $response->body()]);

            throw new RagUnavailableException;
        }

        return new RagAnswer($answer, $this->normalizeSources($response->json('sources', [])));
    }

    /** Cek kesehatan layanan RAG (GET /health). */
    public function health(): ?array
    {
        try {
            return Http::baseUrl($this->baseUrl)->acceptJson()->timeout(5)->get('/health')->throw()->json();
        } catch (ConnectionException|RequestException) {
            return null;
        }
    }

    /**
     * Terima dua bentuk: ["file.pdf", ...] (PRD) atau
     * [{"id":1,"filename":"file.pdf","content":"...","similarity":0.8}, ...] (db.py).
     *
     * @return array<int, array{chunk_id: ?int, filename: string, content: ?string, similarity: ?float}>
     */
    private function normalizeSources(mixed $sources): array
    {
        if (! is_array($sources)) {
            return [];
        }

        $result = [];
        foreach ($sources as $source) {
            if (is_string($source) && trim($source) !== '') {
                $result[] = ['chunk_id' => null, 'filename' => trim($source), 'content' => null, 'similarity' => null];
            } elseif (is_array($source)) {
                $filename = $source['filename'] ?? $source['sumber'] ?? null;
                if (! is_string($filename) || trim($filename) === '') {
                    continue;
                }
                $result[] = [
                    'chunk_id' => isset($source['id']) && is_numeric($source['id']) ? (int) $source['id'] : null,
                    'filename' => trim($filename),
                    'content' => isset($source['content']) ? (string) $source['content'] : null,
                    'similarity' => isset($source['similarity']) && is_numeric($source['similarity']) ? (float) $source['similarity'] : null,
                ];
            }
        }

        return $result;
    }
}

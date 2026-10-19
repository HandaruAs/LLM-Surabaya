<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class RagDocumentIndexer
{
    public function delete(int $documentId): void
    {
        try {
            $response = Http::timeout(30)->delete(
                rtrim((string) config('services.rag.url'), '/').'/documents/'.$documentId,
            );
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Layanan RAG tidak dapat dihubungi untuk menghapus indeks.', previous: $exception);
        }

        if (! $response->successful() && $response->status() !== 404) {
            throw new RuntimeException('Layanan RAG gagal menghapus indeks dokumen.');
        }
    }

    public function index(int $documentId, string $storedPath, string $originalName): int
    {
        $disk = Storage::disk(config('filesystems.default'));
        $stream = $disk->readStream($storedPath);
        if ($stream === false) {
            throw new RuntimeException('Berkas tidak dapat dibaca dari penyimpanan.');
        }

        try {
            $response = Http::timeout(180)
                ->attach('document', $stream, $originalName)
                ->post(rtrim((string) config('services.rag.url'), '/').'/documents/index', [
                    'document_id' => $documentId,
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Layanan RAG tidak dapat dihubungi.', previous: $exception);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $response->successful() || $response->json('status') !== 'indexed') {
            throw new RuntimeException('Layanan RAG menolak atau gagal mengindeks dokumen.');
        }

        return (int) $response->json('chunks', 0);
    }
}

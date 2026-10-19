<?php

namespace App\Services\Rag;

final class RagAnswer
{
    /**
     * @param  array<int, array{chunk_id: ?int, filename: string, content: ?string, similarity: ?float}>  $chunks
     */
    public function __construct(
        public readonly string $answer,
        public readonly array $chunks = [],
    ) {}

    /** Nama file unik, urut abjad — yang ditampilkan di UI. */
    public function filenames(): array
    {
        $names = array_values(array_unique(array_column($this->chunks, 'filename')));
        sort($names);

        return $names;
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Skema dasar milik layanan Python (sumber: llm_surabaya.sql dari tim AI).
 *
 * Bila database sudah diimpor dari dump tersebut, migration ini TIDAK
 * mengubah apa pun (setiap tabel dicek dulu). Migration ini hanya membuat
 * tabel di database kosong, misalnya database untuk testing.
 *
 * Bila tim AI mengubah skema, perbarui dump dan migration ini bersamaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        if (! Schema::hasTable('rag_documents')) {
            DB::statement(<<<'SQL'
                CREATE TABLE rag_documents (
                    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                    filename text NOT NULL UNIQUE,
                    indexed_at timestamptz NOT NULL DEFAULT now()
                )
            SQL);
        }

        if (! Schema::hasTable('rag_chunks')) {
            DB::statement(<<<'SQL'
                CREATE TABLE rag_chunks (
                    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                    document_id bigint NOT NULL REFERENCES rag_documents(id) ON DELETE CASCADE,
                    chunk_index integer NOT NULL,
                    content text NOT NULL,
                    embedding vector(384) NOT NULL,
                    created_at timestamptz NOT NULL DEFAULT now(),
                    UNIQUE (document_id, chunk_index)
                )
            SQL);
            DB::statement('CREATE INDEX rag_chunks_embedding_hnsw ON rag_chunks USING hnsw (embedding vector_cosine_ops)');
        }

        if (! Schema::hasTable('chat_sessions')) {
            DB::statement(<<<'SQL'
                CREATE TABLE chat_sessions (
                    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                    created_at timestamptz NOT NULL DEFAULT now()
                )
            SQL);
        }

        if (! Schema::hasTable('chat_messages')) {
            DB::statement(<<<'SQL'
                CREATE TABLE chat_messages (
                    id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                    session_id bigint NOT NULL REFERENCES chat_sessions(id) ON DELETE CASCADE,
                    role text NOT NULL CHECK (role IN ('user', 'assistant')),
                    content text NOT NULL,
                    created_at timestamptz NOT NULL DEFAULT now()
                )
            SQL);
            DB::statement('CREATE INDEX chat_messages_session_created_idx ON chat_messages (session_id, created_at)');
        }

        if (! Schema::hasTable('message_sources')) {
            DB::statement(<<<'SQL'
                CREATE TABLE message_sources (
                    message_id bigint NOT NULL REFERENCES chat_messages(id) ON DELETE CASCADE,
                    chunk_id bigint REFERENCES rag_chunks(id) ON DELETE SET NULL,
                    filename_snapshot text NOT NULL,
                    chunk_snapshot text NOT NULL,
                    PRIMARY KEY (message_id, filename_snapshot, chunk_snapshot)
                )
            SQL);
        }
    }

    /** Tabel milik layanan Python tidak dihapus lewat rollback Laravel. */
    public function down(): void
    {
        //
    }
};

"""PostgreSQL persistence and retrieval for the RAG application."""

import os
from pathlib import Path
from typing import Any

import psycopg
from dotenv import load_dotenv
from pgvector.psycopg import register_vector


ENV_FILE = Path(__file__).with_name(".env")


def load_app_env(env_file: Path = ENV_FILE) -> bool:
    """Load the Python application's local environment without overriding the shell."""
    return load_dotenv(env_file, override=False)


load_app_env()


def get_db_connection():
    conn = psycopg.connect(
        host=os.getenv("PGHOST", "localhost"),
        port=os.getenv("PGPORT", "5432"),
        dbname=os.getenv("PGDATABASE", "llm_surabaya"),
        user=os.getenv("PGUSER", "postgres"),
        password=os.getenv("PGPASSWORD"),
    )
    register_vector(conn)
    return conn


def index_document(filename: str, chunks_with_embeddings: list[tuple[str, Any]]) -> int:
    """Atomically replace the chunks belonging to a filename."""
    with get_db_connection() as conn:
        with conn.cursor() as cur:
            cur.execute(
                """INSERT INTO rag_documents (filename, indexed_at)
                   VALUES (%s, NOW())
                   ON CONFLICT (filename) DO UPDATE SET indexed_at = EXCLUDED.indexed_at
                   RETURNING id""",
                (filename,),
            )
            document_id = cur.fetchone()[0]
            cur.execute("DELETE FROM rag_chunks WHERE document_id = %s", (document_id,))
            cur.executemany(
                """INSERT INTO rag_chunks (document_id, chunk_index, content, embedding)
                   VALUES (%s, %s, %s, %s)""",
                [
                    (document_id, index, text, embedding)
                    for index, (text, embedding) in enumerate(chunks_with_embeddings)
                ],
            )
    return len(chunks_with_embeddings)


def index_admin_document(
    document_id: int, filename: str, chunks_with_embeddings: list[tuple[str, Any]]
) -> int:
    """Replace an admin document's chunks using its stable external document ID."""
    indexed_filename = f"admin:{document_id}:{Path(filename).name}"
    previous_filename_pattern = f"admin:{document_id}:%"
    with get_db_connection() as conn, conn.cursor() as cur:
        cur.execute(
            """INSERT INTO rag_documents (filename, indexed_at)
               VALUES (%s, NOW())
               ON CONFLICT (filename) DO UPDATE SET indexed_at = EXCLUDED.indexed_at
               RETURNING id""",
            (indexed_filename,),
        )
        indexed_document_id = cur.fetchone()[0]
        cur.execute(
            "DELETE FROM rag_documents WHERE filename LIKE %s AND id <> %s",
            (previous_filename_pattern, indexed_document_id),
        )
        cur.execute("DELETE FROM rag_chunks WHERE document_id = %s", (indexed_document_id,))
        cur.executemany(
            """INSERT INTO rag_chunks (document_id, chunk_index, content, embedding)
               VALUES (%s, %s, %s, %s)""",
            [
                (indexed_document_id, index, text, embedding)
                for index, (text, embedding) in enumerate(chunks_with_embeddings)
            ],
        )
    return len(chunks_with_embeddings)


def delete_admin_document(document_id: int) -> bool:
    """Remove indexed chunks for one admin document; chat snapshots remain intact."""
    with get_db_connection() as conn, conn.cursor() as cur:
        cur.execute(
            "DELETE FROM rag_documents WHERE filename LIKE %s",
            (f"admin:{document_id}:%",),
        )
        return cur.rowcount > 0


def count_chunks() -> int:
    with get_db_connection() as conn, conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) FROM rag_chunks")
        return cur.fetchone()[0]


def search_chunks(
    embedding: Any, top_k: int = 3, min_similarity: float = 0.60
) -> list[dict[str, Any]]:
    with get_db_connection() as conn, conn.cursor() as cur:
        cur.execute(
            """SELECT rc.id, rc.content, rd.filename,
                      1 - (rc.embedding <=> %s::vector) AS similarity
               FROM rag_chunks AS rc
               JOIN rag_documents AS rd ON rd.id = rc.document_id
               WHERE 1 - (rc.embedding <=> %s::vector) >= %s
               ORDER BY rc.embedding <=> %s::vector
               LIMIT %s""",
            (embedding, embedding, min_similarity, embedding, top_k),
        )
        return [
            {"id": row[0], "content": row[1], "filename": row[2], "similarity": float(row[3])}
            for row in cur.fetchall()
        ]


def create_chat_session() -> int:
    with get_db_connection() as conn, conn.cursor() as cur:
        cur.execute("INSERT INTO chat_sessions DEFAULT VALUES RETURNING id")
        return cur.fetchone()[0]


def save_chat_turn(session_id: int, question: str, answer: str, sources: list[dict[str, Any]]) -> None:
    with get_db_connection() as conn, conn.cursor() as cur:
        cur.execute(
            "INSERT INTO chat_messages (session_id, role, content) VALUES (%s, 'user', %s)",
            (session_id, question),
        )
        cur.execute(
            """INSERT INTO chat_messages (session_id, role, content)
               VALUES (%s, 'assistant', %s) RETURNING id""",
            (session_id, answer),
        )
        message_id = cur.fetchone()[0]
        cur.executemany(
            """INSERT INTO message_sources
               (message_id, chunk_id, filename_snapshot, chunk_snapshot)
               VALUES (%s, %s, %s, %s)""",
            [
                (message_id, source["id"], source["filename"], source["content"])
                for source in sources
            ],
        )

import os
import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

from python_app.db import load_app_env


class EnvConfigurationTests(unittest.TestCase):
    def test_loads_python_app_env_without_overwriting_existing_environment(self):
        with tempfile.TemporaryDirectory() as temp_dir:
            env_file = Path(temp_dir) / ".env"
            env_file.write_text("PGHOST=from-file\nPGPORT=5432\n", encoding="utf-8")
            with patch.dict(os.environ, {"PGHOST": "existing"}, clear=True):
                loaded = load_app_env(env_file)
                self.assertTrue(loaded)
                self.assertEqual(os.environ["PGHOST"], "existing")
                self.assertEqual(os.environ["PGPORT"], "5432")


sys.path.insert(0, str(Path(__file__).resolve().parents[2]))

from python_app.db import index_document


class IndexDocumentTests(unittest.TestCase):
    def test_chunk_text_limits_chunks_to_ninety_words(self):
        from python_app.main import chunk_text

        chunks = list(chunk_text("kata " * 200))
        self.assertTrue(chunks)
        self.assertLessEqual(max(len(chunk.split()) for chunk in chunks), 90)


    def test_search_chunks_casts_embedding_parameters_to_vector(self):
        from unittest.mock import MagicMock, patch
        from python_app.db import search_chunks

        cursor = MagicMock()
        cursor.fetchall.return_value = []
        connection = MagicMock()
        connection.cursor.return_value.__enter__.return_value = cursor
        with patch("python_app.db.get_db_connection") as connect:
            connect.return_value.__enter__.return_value = connection
            search_chunks([0.1] * 384, top_k=2)
        sql = cursor.execute.call_args.args[0]
        self.assertEqual(sql.count("::vector"), 3)

    def test_search_chunks_returns_ranked_source_records(self):
        from unittest.mock import MagicMock, patch
        from python_app.db import search_chunks

        rows = [(7, "relevant text", "budget.txt", 0.92)]
        cursor = MagicMock()
        cursor.fetchall.return_value = rows
        connection = MagicMock()
        connection.cursor.return_value.__enter__.return_value = cursor
        with patch("python_app.db.get_db_connection") as connect:
            connect.return_value.__enter__.return_value = connection
            results = search_chunks([0.1] * 384, top_k=2)
        self.assertEqual(results, [{"id": 7, "content": "relevant text", "filename": "budget.txt", "similarity": 0.92}])

    def test_search_chunks_filters_results_below_minimum_similarity(self):
        from unittest.mock import MagicMock, patch
        from python_app.db import search_chunks

        rows = [
            (1, "good match", "budget.pdf", 0.71),
            (2, "weak match", "budget.pdf", 0.39),
        ]
        cursor = MagicMock()
        cursor.fetchall.return_value = [row for row in rows if row[3] >= 0.6]
        connection = MagicMock()
        connection.cursor.return_value.__enter__.return_value = cursor
        with patch("python_app.db.get_db_connection") as connect:
            connect.return_value.__enter__.return_value = connection
            search_chunks([0.1] * 384, min_similarity=0.6)
        sql, params = cursor.execute.call_args.args
        self.assertIn("WHERE 1 - (rc.embedding <=> %s::vector) >= %s", sql)
        self.assertIn(0.6, params)
        self.assertEqual([row[1] for row in cursor.fetchall.return_value], ["good match"])

    def test_reindex_replaces_chunks_for_same_filename(self):
        filename = "__test_reindex_document__.txt"
        first_chunks = [("old chunk", [0.0] * 384)]
        second_chunks = [("new chunk", [0.0] * 384), ("another chunk", [0.0] * 384)]

        index_document(filename, first_chunks)
        index_document(filename, second_chunks)

        from python_app.db import get_db_connection

        conn = get_db_connection()
        try:
            with conn.cursor() as cur:
                cur.execute(
                    """SELECT rd.id, COUNT(rc.id), ARRAY_AGG(rc.content ORDER BY rc.chunk_index)
                       FROM rag_documents rd
                       LEFT JOIN rag_chunks rc ON rc.document_id = rd.id
                       WHERE rd.filename = %s
                       GROUP BY rd.id""",
                    (filename,),
                )
                row = cur.fetchone()
                self.assertIsNotNone(row)
                self.assertEqual(row[1], 2)
                self.assertEqual(row[2], ["new chunk", "another chunk"])
        finally:
            if row:
                with conn.cursor() as cur:
                    cur.execute("DELETE FROM rag_documents WHERE filename = %s", (filename,))
                conn.commit()
            conn.close()


if __name__ == "__main__":
    unittest.main()

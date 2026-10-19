import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2]))

from python_app.db import index_document


class IndexDocumentTests(unittest.TestCase):
    def test_reindex_replaces_chunks_for_same_filename(self):
        filename = "__test_reindex_document__.txt"
        first_chunks = [("old chunk", [0.0] * 384)]
        second_chunks = [("new chunk", [0.0] * 384), ("another chunk", [0.0] * 384)]

        index_document(filename, first_chunks)
        index_document(filename, second_chunks)

        from db import get_db_connection

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

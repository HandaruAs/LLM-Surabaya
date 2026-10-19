import unittest
from unittest.mock import MagicMock, patch

from python_app.db import index_admin_document


class AdminDocumentIdentityTests(unittest.TestCase):
    def test_reindex_same_document_id_replaces_previous_filename(self):
        cursor = MagicMock()
        cursor.fetchone.return_value = (12,)
        connection = MagicMock()
        connection.cursor.return_value.__enter__.return_value = cursor

        with patch("python_app.db.get_db_connection") as connect:
            connect.return_value.__enter__.return_value = connection
            index_admin_document(12, "revisi.pdf", [("new text", [0.0] * 384)])

        insert_sql, insert_params = cursor.execute.call_args_list[0].args
        self.assertIn("INSERT INTO rag_documents", insert_sql)
        self.assertEqual(insert_params, ("admin:12:revisi.pdf",))
        cleanup_sql, cleanup_params = cursor.execute.call_args_list[1].args
        self.assertIn("DELETE FROM rag_documents WHERE filename LIKE %s AND id <> %s", cleanup_sql)
        self.assertEqual(cleanup_params, ("admin:12:%", 12))
        self.assertEqual(cursor.executemany.call_args.args[1][0][0], 12)


if __name__ == "__main__":
    unittest.main()

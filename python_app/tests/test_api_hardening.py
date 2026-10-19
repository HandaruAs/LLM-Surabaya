import io
import unittest
from unittest.mock import patch

from pathlib import Path
import sys
sys.path.insert(0, str(Path(__file__).resolve().parents[2]))

from fastapi.testclient import TestClient

from python_app.api import app, answer_question


class ApiHardeningTests(unittest.TestCase):
    def test_history_items_are_limited_by_characters(self):
        with patch("python_app.api.generate_answer", return_value=("ok", [])) as generate:
            answer_question("q", [{"role": "user", "content": "x" * 2500}])
        self.assertLessEqual(len(generate.call_args.args[1][0]["content"]), 2000)

    def test_malformed_pdf_is_rejected_before_indexing(self):
        client = TestClient(app)
        with patch("python_app.api.index_uploaded_document") as indexer:
            response = client.post("/documents/index", data={"document_id": 1}, files={"document": ("bad.pdf", b"not a pdf", "application/pdf")})
        self.assertEqual(response.status_code, 422)
        indexer.assert_not_called()

    def test_malformed_xlsx_is_rejected_before_indexing(self):
        client = TestClient(app)
        with patch("python_app.api.index_uploaded_document") as indexer:
            response = client.post("/documents/index", data={"document_id": 1}, files={"document": ("bad.xlsx", b"not a workbook", "application/octet-stream")})
        self.assertEqual(response.status_code, 422)
        indexer.assert_not_called()

import unittest
from unittest.mock import patch

from fastapi.testclient import TestClient

from python_app.api import app


class ChatApiTests(unittest.TestCase):
    def test_answer_question_returns_answer_and_sources_separately(self):
        from python_app.api import answer_question

        with patch("python_app.api.generate_answer") as generate_answer:
            generate_answer.return_value = ("Jawaban tanpa sumber di teks", [{"id": 3, "filename": "perda.pdf", "content": "chunk", "similarity": 0.8}])
            result = answer_question("Apa isi APBD?", [])
        self.assertEqual(result["answer"], "Jawaban tanpa sumber di teks")
        self.assertEqual(result["sources"], [{"id": 3, "filename": "perda.pdf", "content": "chunk", "similarity": 0.8}])

    def test_answer_question_drops_malformed_history_entries(self):
        from python_app.api import answer_question

        with patch("python_app.api.generate_answer", return_value=("ok", [])) as generate_answer:
            answer_question("Tanya", [{"role": "tool", "content": "ignore"}, {"role": "user", "content": "Sebelumnya"}])
        generate_answer.assert_called_once_with("Tanya", [{"role": "user", "content": "Sebelumnya"}])

    def test_chat_endpoint_logs_internal_error_and_returns_generic_503(self):
        client = TestClient(app)
        with patch("python_app.api.answer_question", side_effect=RuntimeError("diagnostic failure")):
            with self.assertLogs("python_app.api", level="ERROR") as captured:
                response = client.post("/chat", json={"question": "Tanya", "history": []})
        self.assertEqual(response.status_code, 503)
        self.assertEqual(response.json(), {"detail": "Pemrosesan pertanyaan gagal."})
        self.assertIn("diagnostic failure", captured.output[0])

    def test_chat_endpoint_returns_laravel_response_shape(self):
        client = TestClient(app)
        with patch("python_app.api.answer_question") as answer_question:
            answer_question.return_value = {
                "answer": "Jawaban uji",
                "sources": ["perda.pdf"],
            }
            response = client.post(
                "/chat",
                json={"question": "Apa isi APBD?", "history": [], "lang": "id"},
            )
        self.assertEqual(response.status_code, 200)
        self.assertEqual(
            response.json(),
            {
                "answer": "Jawaban uji",
                "sources": ["perda.pdf"],
                "suggestions": [],
            },
        )
        answer_question.assert_called_once_with("Apa isi APBD?", [])


if __name__ == "__main__":
    unittest.main()

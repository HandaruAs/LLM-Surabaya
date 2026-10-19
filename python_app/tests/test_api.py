import unittest
from unittest.mock import patch

from fastapi.testclient import TestClient

from python_app.api import app


class ChatApiTests(unittest.TestCase):
    def test_answer_question_returns_answer_and_sources_separately(self):
        from python_app.api import answer_question

        with patch("python_app.api.generate_answer") as generate_answer:
            generate_answer.return_value = ("Jawaban tanpa sumber di teks", [{"filename": "perda.pdf"}])
            result = answer_question("Apa isi APBD?", [])
        self.assertEqual(result["answer"], "Jawaban tanpa sumber di teks")
        self.assertEqual(result["sources"], ["perda.pdf"])

    def test_answer_question_drops_malformed_history_entries(self):
        from python_app.api import answer_question

        with patch("python_app.api.generate_answer", return_value=("ok", [])) as generate_answer:
            answer_question("Tanya", [{"role": "tool", "content": "ignore"}, {"role": "user", "content": "Sebelumnya"}])
        generate_answer.assert_called_once_with("Tanya", [{"role": "user", "content": "Sebelumnya"}])

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

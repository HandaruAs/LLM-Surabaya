from typing import Any

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field

from .main import generate_answer

app = FastAPI(title="LLM Surabaya RAG API")


class ChatRequest(BaseModel):
    question: str = Field(min_length=1, max_length=2000)
    history: list[dict[str, Any]] = Field(default_factory=list, max_length=20)
    lang: str = Field(default="id", pattern="^(id|en)$")


def answer_question(question: str, history: list[dict[str, Any]]) -> dict[str, Any]:
    valid_history = [
        {"role": item["role"], "content": item["content"]}
        for item in history
        if item.get("role") in {"user", "assistant"}
        and isinstance(item.get("content"), str)
    ][-10:]
    answer, sources = generate_answer(question, valid_history)
    return {"answer": answer, "sources": [source["filename"] for source in sources]}


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/chat")
def chat(request: ChatRequest) -> dict[str, Any]:
    try:
        result = answer_question(request.question, request.history)
    except Exception as exc:
        raise HTTPException(status_code=503, detail="Pemrosesan pertanyaan gagal.") from exc
    return {**result, "suggestions": []}

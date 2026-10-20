import io
import logging
import zipfile
from typing import Any

from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from openpyxl import load_workbook
from pydantic import BaseModel, Field
from pypdf import PdfReader

from .main import delete_uploaded_document, generate_answer, index_uploaded_document

MAX_UPLOAD_BYTES = 50 * 1024 * 1024
UPLOAD_READ_CHUNK_BYTES = 1024 * 1024


def read_bounded_upload(upload) -> bytes:
    parts = []
    total = 0
    while total <= MAX_UPLOAD_BYTES:
        chunk = upload.read(min(UPLOAD_READ_CHUNK_BYTES, MAX_UPLOAD_BYTES + 1 - total))
        if not chunk:
            break
        total += len(chunk)
        if total > MAX_UPLOAD_BYTES:
            raise HTTPException(status_code=413, detail="Ukuran dokumen melebihi 50 MB.")
        parts.append(chunk)
    return b"".join(parts)


def validate_document_content(filename: str, content: bytes) -> None:
    suffix = filename.lower().rsplit(".", 1)[-1]
    try:
        if suffix == "pdf":
            if not content.startswith(b"%PDF-"):
                raise ValueError("Invalid PDF signature")
            PdfReader(io.BytesIO(content), strict=True)
        elif suffix == "xlsx":
            with zipfile.ZipFile(io.BytesIO(content)) as archive:
                required = {"[Content_Types].xml", "xl/workbook.xml"}
                if not required.issubset(archive.namelist()):
                    raise ValueError("Invalid XLSX structure")
            workbook = load_workbook(io.BytesIO(content), read_only=True, data_only=True)
            workbook.close()
    except Exception as exc:
        raise HTTPException(status_code=422, detail="Isi file bukan PDF atau XLSX yang valid.") from exc

logger = logging.getLogger(__name__)

app = FastAPI(title="LLM Surabaya RAG API")


class ChatRequest(BaseModel):
    question: str = Field(min_length=1, max_length=2000)
    history: list[dict[str, Any]] = Field(default_factory=list, max_length=20)
    lang: str = Field(default="id", pattern="^(id|en)$")


def answer_question(question: str, history: list[dict[str, Any]]) -> dict[str, Any]:
    valid_history = [
        {"role": item["role"], "content": item["content"][:2000]}
        for item in history
        if item.get("role") in {"user", "assistant"}
        and isinstance(item.get("content"), str)
    ][-10:]
    answer, sources = generate_answer(question, valid_history)
    return {"answer": answer, "sources": sources}


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/documents/index")
def index_document_endpoint(
    document_id: int = Form(gt=0),
    document: UploadFile = File(),
) -> dict[str, Any]:
    filename = document.filename or ""
    if not filename.lower().endswith((".pdf", ".xlsx")):
        raise HTTPException(status_code=422, detail="Format harus PDF atau XLSX.")
    content = read_bounded_upload(document.file)
    validate_document_content(filename, content)
    try:
        result = index_uploaded_document(document_id, filename, content)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc
    except Exception as exc:
        logger.exception("Gagal mengindeks dokumen ID %s", document_id)
        raise HTTPException(status_code=503, detail="Pengindeksan dokumen gagal.") from exc
    return {"status": "indexed", **result}


@app.delete("/documents/{document_id}")
def delete_document_endpoint(document_id: int) -> dict[str, str]:
    try:
        deleted = delete_uploaded_document(document_id)
    except Exception as exc:
        logger.exception("Gagal menghapus indeks dokumen ID %s", document_id)
        raise HTTPException(status_code=503, detail="Penghapusan indeks gagal.") from exc
    if not deleted:
        raise HTTPException(status_code=404, detail="Indeks dokumen tidak ditemukan.")
    return {"status": "deleted"}


@app.post("/chat")
def chat(request: ChatRequest) -> dict[str, Any]:
    try:
        result = answer_question(request.question, request.history)
    except Exception as exc:
        logger.exception("Pemrosesan pertanyaan gagal")
        raise HTTPException(status_code=503, detail="Pemrosesan pertanyaan gagal.") from exc
    return {**result, "suggestions": []}

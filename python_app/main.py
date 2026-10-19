"""
Chatbot keuangan sederhana (RAG): dokumen -> PostgreSQL/pgvector -> Ollama.

Cara pakai:
    python main.py index   # baca dokumen di folder data/ lalu simpan ke PostgreSQL
    python main.py         # mulai tanya-jawab
"""

import sys
from pathlib import Path

import ollama
from pypdf import PdfReader
from sentence_transformers import SentenceTransformer

try:
    from .db import count_chunks, index_document, search_chunks
except ImportError:
    from db import count_chunks, index_document, search_chunks


EMBED_MODEL = "paraphrase-multilingual-MiniLM-L12-v2"
_embedding_model = None


def get_embedding_model():
    global _embedding_model
    if _embedding_model is None:
        _embedding_model = SentenceTransformer(EMBED_MODEL)
    return _embedding_model


def embed_text(text: str) -> list[float]:
    return get_embedding_model().encode(text).tolist()


EMBEDDING_DIMENSION = 384
BASE_DIR = Path(__file__).parent
DATA_DIR = BASE_DIR / "data"
LLM_MODEL = "gemma4:e2b"
CHUNK_WORDS = 90
CHUNK_OVERLAP_WORDS = 15
EMBED_BATCH_SIZE = 16
TOP_K = 3
NUM_CTX = 512
THINK = False
SHOW_CONTEXT = False

SYSTEM_PROMPT = """Kamu adalah asisten edukasi keuangan untuk masyarakat Surabaya.
Aturan:
- Jawab dalam bahasa Indonesia yang sederhana dan jelas.
- Untuk pertanyaan umum tentang keuangan, kamu boleh memakai pengetahuan umum model.
- Jika pertanyaan meminta informasi dari dokumen Pemkot Surabaya, gunakan hanya konteks dokumen yang diberikan.
- Jika informasi yang diminta tentang dokumen tidak ada di konteks, katakan bahwa informasi tersebut belum tersedia.
- Jangan merekomendasikan produk investasi atau perusahaan tertentu.
- Akhiri dengan pengingat singkat bahwa ini bukan nasihat keuangan profesional."""


def validate_embedding_model():
    dimension = get_embedding_model().get_sentence_embedding_dimension()
    if dimension != EMBEDDING_DIMENSION:
        raise RuntimeError(
            f"Model embedding menghasilkan {dimension} dimensi, database mengharapkan {EMBEDDING_DIMENSION}"
        )


def warm_up_llm():
    print(f"Memuat model {LLM_MODEL} ke memori, tunggu sebentar...")
    try:
        ollama.chat(
            model=LLM_MODEL,
            messages=[{"role": "user", "content": "hi"}],
            options={"num_ctx": NUM_CTX, "num_predict": 1},
            think=THINK,
            keep_alive=0,
        )
    except Exception as exc:
        print(f"[Error] Gagal memuat model: {exc}")
        sys.exit(1)


# Load LLM sebelum embedding untuk mengurangi perebutan RAM.
if __name__ == "__main__" and len(sys.argv) == 1:
    warm_up_llm()

if __name__ == "__main__":
    validate_embedding_model()



# ---------- Tahap 1: memproses dokumen ----------
def read_file(path: Path) -> str:
    if path.suffix.lower() == ".pdf":
        reader = PdfReader(str(path))
        return "\n".join(page.extract_text() or "" for page in reader.pages)
    return path.read_text(encoding="utf-8")


def chunk_text(text: str):
    words = text.split()
    step = CHUNK_WORDS - CHUNK_OVERLAP_WORDS
    for start in range(0, len(words), step):
        chunk_words = words[start : start + CHUNK_WORDS]
        if chunk_words:
            yield " ".join(chunk_words)


def index_documents():
    files = [p for p in sorted(DATA_DIR.glob("*")) if p.suffix.lower() in {".txt", ".md", ".pdf"}]
    if not files:
        print(f"Belum ada dokumen di {DATA_DIR}. Taruh file .txt/.md/.pdf di sana dulu.")
        return

    for file in files:
        chunks = list(chunk_text(read_file(file)))
        if not chunks:
            print(f"- {file.name}: kosong atau teks tidak terbaca, dilewati")
            continue

        model = get_embedding_model()
        chunks_with_embeddings = []
        for start in range(0, len(chunks), EMBED_BATCH_SIZE):
            batch = chunks[start : start + EMBED_BATCH_SIZE]
            vectors = model.encode(batch, batch_size=EMBED_BATCH_SIZE).tolist()
            chunks_with_embeddings.extend(zip(batch, vectors))
        saved_count = index_document(file.name, chunks_with_embeddings)
        print(f"- {file.name}: {saved_count} potongan disimpan")

    print(f"Selesai. Total potongan di database: {count_chunks()}")


# ---------- Tahap 2: tanya-jawab ----------
def generate_answer(question: str, history: list[dict] | None = None) -> tuple[str, list[dict]]:
    records = search_chunks(embed_text(question), top_k=TOP_K)
    context = "\n\n---\n\n".join(record["content"] for record in records)
    sources = sorted({record["filename"] for record in records})
    source_names = [{"filename": name} for name in sources]

    if SHOW_CONTEXT:
        print("\n[DEBUG] Potongan yang diambil:")
        for i, record in enumerate(records, start=1):
            print(
                f"  {i}. (kemiripan {record['similarity']:.3f}; "
                f"sumber {record['filename']}) {record['content'][:150]}..."
            )

    messages = [{"role": "system", "content": SYSTEM_PROMPT}]
    for item in (history or [])[-10:]:
        if item.get("role") in {"user", "assistant"} and isinstance(item.get("content"), str):
            messages.append({"role": item["role"], "content": item["content"][:2000]})
    messages.append({
        "role": "user",
        "content": (
            "Konteks dokumen Pemkot Surabaya (mungkin kosong):\n"
            f"{context or '[Tidak ada konteks dokumen yang cocok.]'}"
            f"\n\nPertanyaan: {question}"
        ),
    })
    response = ollama.chat(
        model=LLM_MODEL,
        messages=messages,
        options={"num_ctx": NUM_CTX},
        think=THINK,
        keep_alive=0,
    )
    return response["message"]["content"], source_names


def ask(question: str) -> str:
    answer, sources = generate_answer(question)
    filenames = [source["filename"] for source in sources]
    if filenames:
        return f"{answer}\n\nSumber dokumen: {', '.join(filenames)}"
    return answer


def chat_loop():
    if count_chunks() == 0:
        print("Database dokumen kosong; pertanyaan umum tetap bisa dijawab.\n")

    print("Chatbot keuangan siap. Ketik 'keluar' untuk berhenti.\n")
    while True:
        question = input("Kamu: ").strip()
        if not question:
            continue
        if question.lower() in {"keluar", "exit", "quit"}:
            break
        try:
            print(f"\nBot: {ask(question)}\n")
        except ollama.ResponseError as e:
            print(f"\n[Error dari Ollama] {e.error}\n")
        except Exception as e:  # supaya chatbot tidak langsung crash
            print(f"\n[Error] {e}\n")


if __name__ == "__main__":
    if len(sys.argv) > 1 and sys.argv[1] == "index":
        index_documents()
    else:
        chat_loop()
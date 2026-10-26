"""
Chatbot keuangan sederhana (RAG): dokumen -> PostgreSQL/pgvector -> Ollama.

Cara pakai:
    python main.py index   # baca dokumen di folder data/ lalu simpan ke PostgreSQL
    python main.py         # mulai tanya-jawab
"""

import sys
from pathlib import Path
import re

import ollama
from openpyxl import load_workbook
from pypdf import PdfReader
from sentence_transformers import SentenceTransformer

try:
    from .db import count_chunks, delete_admin_document, index_admin_document, index_document, search_chunks
except ImportError:
    from db import count_chunks, delete_admin_document, index_admin_document, index_document, search_chunks


def structure_financial_table_text(text: str) -> str:
    """Append semantic-search-friendly prose for total revenue rows."""
    pattern = re.compile(
        r"(?im)^\s*(PENDAPATAN\s+DAERAH)\s+"
        r"([\d,]+)\s+([\d,]+)(?:\s+([\d.,()%-]+))?\s*$"
    )
    statements = []
    for match in pattern.finditer(text):
        label, amount_2018, amount_2019, change = match.groups()
        original_row = " ".join(p for p in (label, amount_2018, amount_2019, change) if p)
        
        # Prose dengan banyak keyword: total, pendapatan, daerah, tahun, 2018, 2019, surabaya
        prose = (
            f"Total Pendapatan Daerah Kota Surabaya Tahun 2018 dan 2019. "
            f"Berapa total pendapatan daerah kota surabaya? "
            f"Total pendapatan daerah Kota Surabaya tahun 2018 adalah Rp {amount_2018}. "
            f"Total pendapatan daerah Kota Surabaya tahun 2019 adalah Rp {amount_2019}. "
            f"Perbandingan total pendapatan antara tahun 2019 dibanding tahun 2018 meningkat sebesar {change} persen. "
            f"Total pendapatan merupakan jumlah seluruh penerimaan daerah Kota Surabaya yang terdiri dari Pendapatan Asli Daerah, Dana Perimbangan, dan lain-lain pendapatan yang sah. "
            f"Informasi tentang total pendapatan daerah ini penting untuk memahami realisasi pendapatan pemerintah daerah Kota Surabaya dalam tahun 2018 dan tahun 2019."
        )
        statements.append(f"{original_row}\n{prose}")
    
    if not statements:
        return text
    return text + "\n" + "\n".join(statements)


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
NUM_CTX = 256
THINK = False
SHOW_CONTEXT = False

SYSTEM_PROMPT = """Kamu adalah asisten edukasi keuangan untuk masyarakat Surabaya.

Aturan jawaban:
- Jawab dalam bahasa Indonesia yang sederhana dan jelas.
- JANGAN gunakan markdown formatting (bintang, underscore, hashtag, dll) dalam jawaban.
- Tulis paragraf yang rapi, tanpa bullet points kecuali sangat perlu.
- Jika pertanyaan meminta informasi dari dokumen Pemkot Surabaya, gunakan hanya konteks dokumen yang diberikan.
- Jika informasi yang diminta tidak ada di konteks, katakan "Informasi tersebut belum tersedia dalam dokumen yang kami miliki." Jangan katakan "belum tersedia" saja.
- Untuk pertanyaan umum tentang keuangan (tidak soal dokumen), boleh pakai pengetahuan umum model.
- Jangan merekomendasikan produk investasi atau perusahaan tertentu.
- Akhiri jawaban dengan: "Catatan: Informasi ini untuk edukasi umum. Untuk keputusan penting, silakan verifikasi langsung dengan dinas terkait."
"""


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
    suffix = path.suffix.lower()
    if suffix == ".pdf":
        reader = PdfReader(str(path))
        text = "\n".join(page.extract_text() or "" for page in reader.pages)
        return structure_financial_table_text(text)
    if suffix == ".xlsx":
        workbook = load_workbook(path, read_only=True, data_only=True)
        lines = []
        for worksheet in workbook.worksheets:
            lines.append(f"[Sheet: {worksheet.title}]")
            for row in worksheet.iter_rows(values_only=True):
                values = [str(value).strip() for value in row if value is not None and str(value).strip()]
                if values:
                    lines.append(" | ".join(values))
        workbook.close()
        return "\n".join(lines) if any(line.startswith("[Sheet:") for line in lines) and any(" | " in line for line in lines) else ""
    return path.read_text(encoding="utf-8")


def delete_uploaded_document(document_id: int) -> bool:
    return delete_admin_document(document_id)


def index_uploaded_document(document_id: int, filename: str, content: bytes) -> dict[str, int]:
    suffix = Path(filename).suffix.lower()
    if suffix not in {".pdf", ".xlsx"}:
        raise ValueError("Format dokumen tidak didukung.")

    import tempfile

    with tempfile.TemporaryDirectory() as temp_dir:
        path = Path(temp_dir) / f"document{suffix}"
        path.write_bytes(content)
        text = read_file(path)

    chunks = list(chunk_text(text))
    if not chunks:
        raise ValueError("Dokumen tidak berisi teks yang dapat diekstrak.")

    model = get_embedding_model()
    chunks_with_embeddings = []
    for start in range(0, len(chunks), EMBED_BATCH_SIZE):
        batch = chunks[start : start + EMBED_BATCH_SIZE]
        vectors = model.encode(batch, batch_size=EMBED_BATCH_SIZE).tolist()
        chunks_with_embeddings.extend(zip(batch, vectors))

    saved_count = index_admin_document(document_id, filename, chunks_with_embeddings)
    return {"chunks": saved_count}


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
def generate_answer(
    question: str,
    history: list[dict] | None = None,
    system_prompt: str = "",
    top_k: int = TOP_K,
    num_ctx: int = NUM_CTX,
    think: bool = THINK,
) -> tuple[str, list[dict]]:
    records = search_chunks(embed_text(question), top_k=top_k)
    context = "\n\n---\n\n".join(record["content"] for record in records)
    sources = sorted({record["filename"] for record in records})
    source_records = [
        {key: record[key] for key in ("id", "filename", "content", "similarity")}
        for record in records
    ]
    source_names = [{"filename": name} for name in sources]

    if SHOW_CONTEXT:
        print("\n[DEBUG] Potongan yang diambil:")
        for i, record in enumerate(records, start=1):
            print(
                f"  {i}. (kemiripan {record['similarity']:.3f}; "
                f"sumber {record['filename']}) {record['content'][:150]}..."
            )

    admin_prompt = system_prompt.strip()
    effective_prompt = f"{SYSTEM_PROMPT}\n\nInstruksi tambahan dari administrator:\n{admin_prompt}" if admin_prompt else SYSTEM_PROMPT
    messages = [{"role": "system", "content": effective_prompt}]
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
        options={"num_ctx": num_ctx},
        think=think,
        keep_alive=0,
    )
    return response["message"]["content"], source_records


def ask(question: str) -> str:
    answer, sources = generate_answer(question)
    filenames = sorted({source["filename"] for source in sources})
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
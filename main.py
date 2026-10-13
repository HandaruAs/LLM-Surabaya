"""
Chatbot keuangan sederhana (RAG): dokumen -> Chroma -> Llama via Ollama.

Cara pakai:
    python main.py index   # baca dokumen di folder data/ lalu simpan ke Chroma
    python main.py         # mulai tanya-jawab
"""

import sys
from pathlib import Path

import chromadb
import ollama
from chromadb.utils import embedding_functions
from pypdf import PdfReader

# ---------- Pengaturan ----------
BASE_DIR = Path(__file__).parent          # folder tempat main.py berada
DATA_DIR = BASE_DIR / "data"              # dokumen sumber (.txt, .md, .pdf)
DB_DIR = BASE_DIR / "chroma_db"           # database vektor (dibuat otomatis)

EMBED_MODEL = "paraphrase-multilingual-MiniLM-L12-v2"  # mendukung bahasa Indonesia
LLM_MODEL = "gemma4:e2b"                   # laptop RAM 8GB: "gemma4:e2b" | RAM 24GB+: "gemma4:26b"
CHUNK_SIZE = 1000                         # panjang tiap potongan (karakter)
CHUNK_OVERLAP = 200                       # tumpang tindih antar potongan
TOP_K = 3                                 # jumlah potongan yang diambil per pertanyaan

SYSTEM_PROMPT = """Kamu adalah asisten edukasi keuangan untuk masyarakat Surabaya.
Aturan:
- Jawab HANYA berdasarkan konteks yang diberikan.
- Kalau jawabannya tidak ada di konteks, katakan bahwa informasinya belum tersedia.
- Jangan merekomendasikan produk investasi atau perusahaan tertentu.
- Gunakan bahasa Indonesia yang sederhana dan jelas.
- Akhiri dengan pengingat singkat bahwa ini bukan nasihat keuangan profesional."""

# ---------- Database vektor ----------
embed_fn = embedding_functions.SentenceTransformerEmbeddingFunction(model_name=EMBED_MODEL)
client = chromadb.PersistentClient(path=str(DB_DIR))
collection = client.get_or_create_collection(
    name="dokumen_keuangan", embedding_function=embed_fn
)


# ---------- Tahap 1: memproses dokumen ----------
def read_file(path: Path) -> str:
    if path.suffix.lower() == ".pdf":
        reader = PdfReader(str(path))
        return "\n".join(page.extract_text() or "" for page in reader.pages)
    return path.read_text(encoding="utf-8")


def chunk_text(text: str):
    step = CHUNK_SIZE - CHUNK_OVERLAP
    for i in range(0, len(text), step):
        chunk = text[i : i + CHUNK_SIZE].strip()
        if chunk:
            yield chunk


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

        collection.delete(where={"sumber": file.name})  # hapus versi lama dokumen ini
        collection.upsert(
            ids=[f"{file.name}-{i}" for i in range(len(chunks))],
            documents=chunks,
            metadatas=[{"sumber": file.name} for _ in chunks],
        )
        print(f"- {file.name}: {len(chunks)} potongan disimpan")

    print(f"Selesai. Total potongan di database: {collection.count()}")


# ---------- Tahap 2: tanya-jawab ----------
def ask(question: str) -> str:
    results = collection.query(query_texts=[question], n_results=TOP_K)
    docs = results["documents"][0]
    sources = sorted({m["sumber"] for m in results["metadatas"][0]})

    context = "\n\n---\n\n".join(docs)
    user_prompt = f"Konteks:\n{context}\n\nPertanyaan: {question}"

    response = ollama.chat(
        model=LLM_MODEL,
        messages=[
            {"role": "system", "content": SYSTEM_PROMPT},
            {"role": "user", "content": user_prompt},
        ],
    )
    answer = response["message"]["content"]
    return f"{answer}\n\nSumber: {', '.join(sources)}"


def chat_loop():
    if collection.count() == 0:
        print("Database masih kosong. Jalankan dulu: python main.py index")
        return

    print("Chatbot keuangan siap. Ketik 'keluar' untuk berhenti.\n")
    while True:
        question = input("Kamu: ").strip()
        if not question:
            continue
        if question.lower() in {"keluar", "exit", "quit"}:
            break
        print(f"\nBot: {ask(question)}\n")


if __name__ == "__main__":
    if len(sys.argv) > 1 and sys.argv[1] == "index":
        index_documents()
    else:
        chat_loop()
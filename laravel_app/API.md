# API Chat — Tanya Keuangan Surabaya (Laravel)

Mengikuti PRD Backend v1.0 bagian 8.1. Database: PostgreSQL bersama `python_app`,
skema dasar dari `llm_surabaya.sql` (tim AI).

## Menjalankan

```bash
# 1. Impor skema dari tim AI (sekali saja) — butuh ekstensi pgvector
createdb -U postgres llm_surabaya
psql -U postgres -d llm_surabaya -f llm_surabaya.sql

# 2. Laravel
cd laravel_app
composer install            # butuh PHP 8.4 (sesuai composer.lock)
cp .env.example .env        # isi DB_PASSWORD dan RAG_SERVICE_URL
php artisan key:generate
php artisan migrate         # hanya MENAMBAH kolom web, data tim AI tidak diubah
php artisan serve           # http://127.0.0.1:8000
```

> Dump dibuat dengan PostgreSQL 18. Bila `psql` kamu versi lebih lama dan error di
> baris `\restrict` / `\unrestrict` / `SET transaction_timeout`, hapus baris itu lalu impor ulang.

> **Jangan** jalankan `php artisan migrate:fresh` atau `db:wipe` di database bersama:
> perintah itu menghapus SEMUA tabel, termasuk `rag_chunks` milik tim AI.

Layanan RAG Python harus jalan di `RAG_SERVICE_URL` (default `http://127.0.0.1:8001`)
dengan endpoint `POST /chat` dan `GET /health` (PRD 8.2).

Tes (mengosongkan database, jadi selalu pakai database terpisah):

```bash
createdb -U postgres llm_surabaya_test
DB_DATABASE=llm_surabaya_test php artisan test
```

## Tampilan

`resources/views/chat.blade.php` (tim frontend) memanggil API ini lewat fungsi `api()` di dalam
`<script>`. Riwayat chat disimpan di PostgreSQL. `localStorage` hanya menyimpan `X-Visitor-Id`,
tanda suka/tidak suka, dan lebar sidebar. Setelah mengubah tampilan, jalankan `npm run build`
(atau `npm run dev` saat mengembangkan).

## Header wajib

Semua endpoint (kecuali `/api/health`) membawa `X-Visitor-Id: <uuid v4>` yang dibuat
frontend sekali lalu disimpan di `localStorage`. Tanpa header valid → 422.

## Endpoint

| Method | Path | Keterangan |
|---|---|---|
| POST | `/api/chat` | `{ "session_id": 12 \| null, "message": "..." }` → jawaban + sumber. Tanpa `session_id` → sesi baru, judul = 40 karakter pertama |
| GET | `/api/sessions` | `{ data: [ {id, title, updated_at} ] }`, terbaru dulu |
| DELETE | `/api/sessions` | Tombol "Hapus semua": hapus semua sesi milik pengunjung ini, 204 |
| GET | `/api/sessions/{id}/messages` | `{ session, data: [ {id, role, content, sources, created_at} ] }`, terlama dulu |
| PATCH | `/api/sessions/{id}` | `{ "title": "..." }` (maks 100 karakter) |
| DELETE | `/api/sessions/{id}` | 204, pesan ikut terhapus |
| POST | `/api/sessions/{id}/retry` | Tombol "Kirim ulang" setelah 503: menjawab pertanyaan terakhir yang belum terjawab **tanpa menyimpannya lagi**. Body kosong, respons sama dengan `POST /api/chat` |
| GET | `/api/health` | `{ api, ai, rag }` — `ai: false` bila layanan Python/Ollama mati |

ID sesi berupa angka (bigint), mengikuti skema tim AI. Catatan: PRD 8.1 menulis UUID.

## Kode error

| Kode | Arti |
|---|---|
| 422 | Validasi gagal: pesan kosong / > 1000 karakter, `session_id` bukan angka, header visitor salah; retry padahal pesan terakhir sudah dijawab |
| 404 | Sesi tidak ada atau milik pengunjung lain |
| 409 | Retry untuk sesi yang sama sedang diproses (klik ganda) |
| 429 | Rate limit (default 10/menit per pengunjung, 30/menit per IP) |
| 503 | Layanan AI mati / timeout / jawaban kosong. Pesan pengguna **tetap tersimpan**, jawaban tidak; body membawa `session_id` dan `title` |

## Tabel

Laravel hanya menambah kolom bertanda **+** (semuanya nullable/berdefault, jadi
`python_app/db.py` tetap jalan):

- `chat_sessions` — `id`, `created_at`, **+`visitor_id`**, **+`title`**, **+`updated_at`**
- `chat_messages` — `id`, `session_id`, `role`, `content`, `created_at`, **+`sources`** (JSONB nama file), **+`response_ms`**
- `message_sources` — `message_id`, `chunk_id` (FK ke `rag_chunks`, jadi null saat reindex), `filename_snapshot`, `chunk_snapshot`

Sesi yang dibuat dari `db.py` (tanpa `visitor_id`) tidak tampil di web.

`sources` dari layanan Python boleh berupa `["file.pdf"]` (PRD) atau objek
`[{id, filename, content, similarity}]` (keluaran `search_chunks()` di `db.py`).
Hanya bentuk objek yang ditulis ke `message_sources`, karena `chunk_snapshot` wajib diisi.

## Alur tombol "Kirim ulang"

1. `POST /api/chat` dijawab **503**. Simpan `session_id` dari body, tampilkan pertanyaan pengguna beserta pesan error dan tombol "Kirim ulang".
2. Saat tombol ditekan, panggil `POST /api/sessions/{session_id}/retry`. Jangan panggil `POST /api/chat` lagi, karena itu akan menyimpan pertanyaan yang sama dua kali.
3. **200**: tampilkan jawabannya. **503**: AI masih mati, tombol tetap tampil. **422**: pertanyaan itu ternyata sudah terjawab, muat ulang pesan sesi.

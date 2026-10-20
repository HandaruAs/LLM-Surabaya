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
php artisan migrate         # hanya MENAMBAH kolom/tabel web, data tim AI tidak diubah
php artisan db:seed         # membuat akun admin dari ADMIN_NAME/ADMIN_EMAIL/ADMIN_PASSWORD di .env
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
`<script>`. Riwayat chat user yang login disimpan di PostgreSQL. `localStorage` hanya menyimpan
tanda suka/tidak suka dan lebar sidebar. Setelah mengubah tampilan, jalankan `npm run build`
(atau `npm run dev` saat mengembangkan).

## Login: tamu, user, dan admin

- **User** login memakai **JWT** (HS256) yang disimpan di cookie `llm_token` (`httpOnly`, `SameSite=Lax`).
  Dibuat dan diperiksa oleh `App\Services\Auth\JwtService`, dikenali lewat guard `jwt` (`config/auth.php`).
- **Admin** tetap memakai **session** (guard `web`), sesuai kode admin yang sudah ada.
- Session Laravel masih dipakai untuk satu hal: menyimpan token CSRF.

| Siapa | Bisa chat | Riwayat | Halaman |
|---|---|---|---|
| Tamu | Ya | **Tidak disimpan.** Percakapan hanya ada di halaman, hilang saat refresh/tutup | — |
| User (`role = user`) | Ya | Disimpan per akun, bisa dibuka dari perangkat lain | `/register`, `/login`, `POST /logout` |
| Admin (`role = admin`) | — | — | `/login` (sama dengan user) → langsung ke `/admin`, `/admin/documents` |

- Registrasi terbuka untuk siapa saja dan selalu menghasilkan `role = user`. Admin hanya dibuat lewat `AdminUserSeeder`.
- **Satu pintu login** di `/login`: akun admin otomatis diarahkan ke `/admin` (memakai session), akun user ke halaman chat (memakai JWT). Alamat lama `/admin/login` diarahkan ke `/login`.
- `/admin/*` dicek `auth:web` + `EnsureUserIsAdmin`: user biasa mendapat 403. Belum login → diarahkan ke `/login`, lalu kembali ke halaman admin yang tadi dibuka.
- Tamu yang sedang mengobrol lalu login: percakapan tamunya tidak dipindahkan (hilang).

### Cara kerja JWT

1. Login/registrasi berhasil → server membuat token `header.payload.signature`.
   Payload: `iss` (APP_URL), `sub` (id user), `ver` (versi token user), `iat`, `nbf`, `exp`, `jti` (id unik token).
2. Token dikirim sebagai cookie `httpOnly`, jadi JavaScript (termasuk script penyerang) tidak bisa membacanya.
3. Setiap request, guard `jwt` memeriksa tanda tangan HMAC-SHA256, algoritma (wajib HS256),
   penerbit, masa berlaku, dan apakah `ver` masih sama dengan `users.token_version`.
4. Logout → `users.token_version` dinaikkan 1 dan cookie dihapus. Semua token lama user itu
   (di perangkat mana pun, termasuk yang sempat tersalin) langsung tidak berlaku.
   Akibatnya, logout di satu perangkat = keluar dari semua perangkat.

| Env | Default | Keterangan |
|---|---|---|
| `JWT_SECRET` | kosong (diturunkan dari `APP_KEY`) | Kunci tanda tangan. Mengganti kunci = semua user ter-logout |
| `JWT_TTL` | 120 | Masa berlaku token (menit) |
| `JWT_REMEMBER_TTL` | 43200 (30 hari) | Masa berlaku bila "Ingat saya" dicentang |

## Middleware keamanan

| Middleware | Fungsi |
|---|---|
| `UseJwtGuard` | Halaman chat, login/register user, dan `/api/*` mengenali user dari JWT |
| `auth:jwt` / `guest:jwt` | Riwayat hanya untuk user login; halaman login/register hanya untuk tamu |
| `auth:web` + `EnsureUserIsAdmin` | `/admin` hanya untuk admin (session); user biasa 403 |
| `ValidateCsrfToken` | Request yang mengubah data wajib membawa token CSRF |
| `throttle` | Batas chat, login user/admin, dan registrasi |
| `SecurityHeaders` | CSP dengan nonce, `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, `Cache-Control: no-store` untuk API, HSTS saat HTTPS |

`<script>` inline di Blade **wajib** diberi `nonce="{{ Vite::cspNonce() }}"`, dan jangan memakai
atribut handler inline seperti `onclick="..."`/`onsubmit="..."` karena diblokir CSP.
Pasang event lewat JavaScript (`addEventListener`).

## Header wajib

Route `/api/*` membaca cookie (JWT dan session CSRF), jadi `fetch` harus mengirim cookie (`credentials: 'same-origin'`)
dan setiap `POST`/`PATCH`/`DELETE` wajib membawa `X-CSRF-TOKEN` dari `<meta name="csrf-token">`.
Tanpa token yang cocok → 419.

## Endpoint

| Method | Path | Keterangan |
|---|---|---|
| POST | `/api/chat` | **User login:** `{ "session_id": 12 \| null, "message": "..." }` → jawaban + sumber, disimpan. Tanpa `session_id` → sesi baru, judul = 40 karakter pertama. **Tamu:** `{ "message": "...", "history": [ {role, content} ] }` → jawaban + sumber, tidak disimpan; `session_id`, `title`, dan `message.id` bernilai `null`. `history` maks 20 item, role `user`/`assistant`; yang diteruskan ke AI hanya 6 terakhir |
| GET | `/api/sessions` | `{ data: [ {id, title, updated_at} ] }`, terbaru dulu |
| DELETE | `/api/sessions` | Tombol "Hapus semua": hapus semua sesi milik user ini, 204 |
| GET | `/api/sessions/{id}/messages` | `{ session, data: [ {id, role, content, sources, created_at} ] }`, terlama dulu |
| PATCH | `/api/sessions/{id}` | `{ "title": "..." }` (maks 100 karakter) |
| DELETE | `/api/sessions/{id}` | 204, pesan ikut terhapus |
| POST | `/api/sessions/{id}/retry` | Tombol "Kirim ulang" setelah 503: menjawab pertanyaan terakhir yang belum terjawab **tanpa menyimpannya lagi**. Body kosong, respons sama dengan `POST /api/chat` |
| GET | `/api/health` | `{ api, ai, rag }` — `ai: false` bila layanan Python/Ollama mati |

Semua endpoint `/api/sessions/*` hanya untuk user yang login (tamu → 401).

ID sesi berupa angka (bigint), mengikuti skema tim AI. Catatan: PRD 8.1 menulis UUID.

## Kode error

| Kode | Arti |
|---|---|
| 401 | Tamu membuka endpoint riwayat, atau mengirim `session_id` |
| 419 | Token CSRF tidak ada / tidak cocok (session halaman kedaluwarsa, muat ulang halaman) |
| 422 | Validasi gagal: pesan kosong / > 1000 karakter, `session_id` bukan angka, `history` tidak valid; retry padahal pesan terakhir sudah dijawab |
| 404 | Sesi tidak ada atau milik user lain |
| 409 | Retry untuk sesi yang sama sedang diproses (klik ganda) |
| 429 | Rate limit (default 10/menit per user, 10/menit per IP untuk tamu, 30/menit total per IP) |
| 503 | Layanan AI mati / timeout / jawaban kosong. User login: pesan pengguna **tetap tersimpan**, jawaban tidak; body membawa `session_id` dan `title`. Tamu: tidak ada yang tersimpan, `session_id: null`, cukup kirim ulang pertanyaannya |

## Tabel

Laravel hanya menambah kolom bertanda **+** (semuanya nullable/berdefault, jadi
`python_app/db.py` tetap jalan):

- `users` — tabel bawaan Laravel, **+`role`** (`user` / `admin`, default `user`)
- `users` juga **+`token_version`** (angka, default 0): naik setiap user logout; JWT dengan `ver` lama ditolak
- `chat_sessions` — `id`, `created_at`, **+`user_id`** (FK ke `users`, akun dihapus → sesinya ikut terhapus), **+`title`**, **+`updated_at`**, **+`visitor_id`** (tidak dipakai lagi sejak ada login)
- `chat_messages` — `id`, `session_id`, `role`, `content`, `created_at`, **+`sources`** (JSONB nama file), **+`response_ms`**
- `message_sources` — `message_id`, `chunk_id` (FK ke `rag_chunks`, jadi null saat reindex), `filename_snapshot`, `chunk_snapshot`

Sesi yang dibuat dari `db.py` (tanpa `user_id`) tidak tampil di web.

`sources` dari layanan Python boleh berupa `["file.pdf"]` (PRD) atau objek
`[{id, filename, content, similarity}]` (keluaran `search_chunks()` di `db.py`).
Hanya bentuk objek yang ditulis ke `message_sources`, karena `chunk_snapshot` wajib diisi.

## Alur tombol "Kirim ulang" (khusus user login)

1. `POST /api/chat` dijawab **503**. Simpan `session_id` dari body, tampilkan pertanyaan pengguna beserta pesan error dan tombol "Kirim ulang".
2. Saat tombol ditekan, panggil `POST /api/sessions/{session_id}/retry`. Jangan panggil `POST /api/chat` lagi, karena itu akan menyimpan pertanyaan yang sama dua kali.
3. **200**: tampilkan jawabannya. **503**: AI masih mati, tombol tetap tampil. **422**: pertanyaan itu ternyata sudah terjawab, muat ulang pesan sesi.

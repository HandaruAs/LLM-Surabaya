<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Auth\UserAuthController;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\UseJwtGuard;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Tolak endpoint lama; seluruh fitur chat kini melalui /api/*.
Route::post('/chat', fn () => abort(404));

// Halaman chat dan login/register/logout user memakai guard 'jwt' (token di cookie).
// Area /admin di bawah TIDAK memakai JWT: admin tetap login lewat session.
Route::middleware(UseJwtGuard::class)->group(function () {
    // Halaman chat. Bisa dibuka tamu maupun user yang login.
    // Semua data (kirim pesan, riwayat) lewat routes/api.php -> /api/...
    Route::view('/', 'chat')->name('chat');

    Route::middleware('guest:jwt')->group(function () {
        Route::get('/login', [UserAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [UserAuthController::class, 'login'])
            ->middleware('throttle:user-login')
            ->name('login.store');
        Route::get('/register', [UserAuthController::class, 'showRegister'])->name('register');
        Route::post('/register', [UserAuthController::class, 'register'])
            ->middleware('throttle:register')
            ->name('register.store');
    });
    Route::post('/logout', [UserAuthController::class, 'logout'])->middleware('auth:jwt')->name('logout');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:web')->group(function () {
        // Login admin kini lewat /login biasa; alamat lama diarahkan ke sana.
        Route::get('/login', fn () => redirect()->route('login'))->name('login');
        Route::post('/login', [AuthController::class, 'store'])
            ->middleware('throttle:admin-login')
            ->name('login.store');
    });

    // 'auth:web' = harus login admin (session), EnsureUserIsAdmin = harus ber-role admin (user biasa -> 403).
    Route::middleware(['auth:web', EnsureUserIsAdmin::class])->group(function () {
        // Area admin hanya untuk kelola dokumen: /admin langsung ke halaman itu.
        Route::get('/', fn () => redirect()->route('admin.documents.index'))->name('dashboard');
        Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
        Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])
            ->whereNumber('document')
            ->name('documents.destroy');
        Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

        // ===================== KONFIGURASI AI + CHATBOT UJI =====================

        // Alamat backend Python. Akan kita samakan dengan yang dipakai chat di langkah berikutnya.
        $pyUrl      = env('PYTHON_API_URL', 'http://127.0.0.1:8001');
        $pyChatPath = '/chat';

        // Data yang dikirim ke Python. Kalau nama field di backend beda, ubah HANYA di sini.
        $toBackend = fn (string $question, array $cfg, string $prompt) => [
            'question'      => $question,
            'system_prompt' => $prompt,
            'top_k'         => $cfg['top_k'],
            'num_ctx'       => $cfg['num_ctx'],
            'think'         => $cfg['think'],
            'show_context'  => $cfg['show_context'],
        ];

        // Membaca konfigurasi (tersimpan di storage/app/private/ai-config.json)
        $aiLoad = function (): array {
            $default = ['role' => '', 'rules' => [], 'top_k' => 3, 'num_ctx' => 512, 'show_context' => false, 'think' => false];
            $disk = Storage::disk('local');
            $saved = $disk->exists('ai-config.json') ? json_decode($disk->get('ai-config.json'), true) : [];
            return array_merge($default, is_array($saved) ? $saved : []);
        };

        // Menyusun instruksi dari peran + aturan yang aktif
        $aiPrompt = function (array $cfg): string {
            $parts = [];
            if (trim($cfg['role']) !== '') {
                $parts[] = trim($cfg['role']);
            }
            $active = array_values(array_filter($cfg['rules'], fn ($r) => !empty($r['on'])));
            if ($active) {
                $parts[] = "Aturan jawaban:\n" . implode("\n", array_map(fn ($r) => '- ' . $r['text'], $active));
            }
            return implode("\n\n", $parts);
        };

        // Halaman Konfigurasi AI
        Route::get('/ai-config', fn () => view('admin.ai-config', ['config' => $aiLoad()]))->name('ai-config');

        Route::put('/ai-config', function (Request $request) {
            $data = $request->validate([
                'role'         => 'nullable|string|max:500',
                'rules'        => 'array|max:50',
                'rules.*.text' => 'required|string|max:500',
                'rules.*.on'   => 'boolean',
                'top_k'        => 'required|integer|min:1|max:10',
                'num_ctx'      => 'required|integer|min:512|max:32768',
                'show_context' => 'boolean',
                'think'        => 'boolean',
            ]);
            $data['role']  = trim($data['role'] ?? '');
            $data['rules'] = array_map(fn ($r) => ['text' => trim($r['text']), 'on' => (bool) ($r['on'] ?? false)], $data['rules'] ?? []);
            Storage::disk('local')->put('ai-config.json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return response()->json(['ok' => true]);
        });

        // Halaman Chatbot AI (uji konfigurasi)
        Route::get('/ai-chat', fn () => view('admin.ai-chat', ['config' => $aiLoad()]))->name('ai-chat');

        Route::post('/ai-chat', function (Request $request) use ($aiLoad, $aiPrompt, $toBackend, $pyUrl, $pyChatPath) {
            $question = $request->validate(['question' => 'required|string|max:1000'])['question'];
            $cfg    = $aiLoad();
            $prompt = $aiPrompt($cfg);
            $start  = microtime(true);

            try {
                $res = Http::timeout(180)->acceptJson()->post(rtrim($pyUrl, '/') . $pyChatPath, $toBackend($question, $cfg, $prompt));
            } catch (ConnectionException $e) {
                return response()->json(['message' => "Backend Python tidak bisa dihubungi di {$pyUrl}. Pastikan uvicorn berjalan."], 502);
            }
            if ($res->failed()) {
                return response()->json(['message' => 'Backend Python membalas error (' . $res->status() . '). Cek terminal uvicorn.'], 502);
            }

            $body = $res->json();
            $answer = is_array($body) ? ($body['answer'] ?? $body['response'] ?? $body['jawaban'] ?? $body['reply'] ?? null) : null;

            return response()->json([
                'answer'     => $answer ?? $res->body(),
                'context'    => is_array($body) ? ($body['context'] ?? $body['contexts'] ?? $body['konteks'] ?? null) : null,
                'prompt'     => $prompt,
                'elapsed_ms' => (int) ((microtime(true) - $start) * 1000),
                // Ringkasan konfigurasi terbaru, dipakai halaman Chatbot AI untuk memperbarui panel kanan.
                'summary'    => [
                    'aktif'   => count(array_filter($cfg['rules'], fn ($r) => !empty($r['on']))),
                    'top_k'   => $cfg['top_k'],
                    'num_ctx' => $cfg['num_ctx'],
                ],
            ]);
        });
    });
});
<?php

use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\UseJwtGuard;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Route /api/* membaca cookie (JWT untuk login, session hanya untuk token CSRF).
        // Karena memakai cookie, request POST/PATCH/DELETE wajib membawa
        // header X-CSRF-TOKEN (diambil dari <meta name="csrf-token"> di halaman).
        $middleware->api(prepend: [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ValidateCsrfToken::class,
            UseJwtGuard::class,
        ]);

        // Pilih guard 'jwt' SEBELUM middleware auth/throttle membaca user-nya.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: UseJwtGuard::class,
        );

        // Header keamanan untuk semua halaman dan API.
        $middleware->append(SecurityHeaders::class);

        // Belum login (user maupun admin): ke satu halaman login yang sama.
        $middleware->redirectGuestsTo(fn () => route('login'));

        // Sudah login tapi membuka halaman login/register: admin -> dashboard admin, user -> chat.
        $middleware->redirectUsersTo(
            fn (Request $request) => $request->user()?->isAdmin() ? route('admin.dashboard') : route('chat')
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Semua error di /api/* dikembalikan sebagai JSON, bukan halaman HTML.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        // 404: sesi tidak ada atau milik pengunjung lain (ModelNotFound diubah
        // Laravel menjadi NotFoundHttpException sebelum sampai di sini).
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                $message = $e->getPrevious() instanceof ModelNotFoundException
                    ? 'Percakapan tidak ditemukan.'
                    : 'Alamat API tidak ditemukan.';

                return response()->json(['message' => $message], 404);
            }
        });

        // 401: endpoint riwayat dibuka tanpa login.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Masuk terlebih dahulu untuk membuka riwayat percakapan.'], 401);
            }
        });

        // 419: token CSRF tidak cocok / session halaman sudah kedaluwarsa.
        // Laravel mengubah TokenMismatchException menjadi HttpException 419 sebelum sampai di sini.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('api/*') && $e->getStatusCode() === 419) {
                return response()->json(['message' => 'Sesi halaman sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.'], 419);
            }
        });

        // 429: terlalu banyak permintaan (FR-16).
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Terlalu banyak pertanyaan. Tunggu sebentar lalu coba lagi.',
                ], 429, $e->getHeaders());
            }
        });
    })->create();

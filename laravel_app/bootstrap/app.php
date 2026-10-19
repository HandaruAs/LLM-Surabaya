<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
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

        // 429: terlalu banyak permintaan (FR-16).
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Terlalu banyak pertanyaan. Tunggu sebentar lalu coba lagi.',
                ], 429, $e->getHeaders());
            }
        });
    })->create();

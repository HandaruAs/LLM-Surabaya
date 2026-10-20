<?php

use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\SessionController;
use Illuminate\Support\Facades\Route;

// Semua route di sini otomatis berprefix /api (lihat bootstrap/app.php).
// Route /api/* mengenali user dari JWT di cookie (guard 'jwt'), dan karena
// memakai cookie, POST/PATCH/DELETE wajib membawa header X-CSRF-TOKEN.

Route::get('/health', HealthController::class);

// Tamu boleh bertanya (tidak disimpan); user login otomatis tersimpan ke riwayat.
Route::post('/chat', [ChatController::class, 'store'])->middleware('throttle:chat');

// Riwayat hanya untuk user yang login. Tamu -> 401.
Route::middleware('auth:jwt')->group(function () {
    Route::get('/sessions', [SessionController::class, 'index']);
    Route::delete('/sessions', [SessionController::class, 'destroyAll']);

    // {id} harus angka bigint positif (maks 18 digit), selain itu 404.
    Route::get('/sessions/{id}/messages', [SessionController::class, 'messages'])->where('id', '[1-9][0-9]{0,17}');
    Route::patch('/sessions/{id}', [SessionController::class, 'update'])->where('id', '[1-9][0-9]{0,17}');
    Route::delete('/sessions/{id}', [SessionController::class, 'destroy'])->where('id', '[1-9][0-9]{0,17}');
    Route::post('/sessions/{id}/retry', [ChatController::class, 'retry'])
        ->where('id', '[1-9][0-9]{0,17}')
        ->middleware('throttle:chat');
});

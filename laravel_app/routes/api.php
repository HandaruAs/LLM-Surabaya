<?php

use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Middleware\EnsureVisitorId;
use Illuminate\Support\Facades\Route;

// Semua route di sini otomatis berprefix /api (lihat bootstrap/app.php).

Route::get('/health', HealthController::class);

Route::middleware(EnsureVisitorId::class)->group(function () {
    Route::post('/chat', [ChatController::class, 'store'])->middleware('throttle:chat');

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

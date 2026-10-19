<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DocumentController;
use Illuminate\Support\Facades\Route;

// Halaman chat. Semua data (kirim pesan, riwayat) lewat routes/api.php -> /api/...
Route::view('/', 'chat')->name('chat');

// Tolak endpoint lama; seluruh fitur chat kini melalui /api/*.
Route::post('/chat', fn () => abort(404));

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'create'])->name('login');
        Route::post('/login', [AuthController::class, 'store'])
            ->middleware('throttle:admin-login')
            ->name('login.store');
    });

    Route::middleware('auth')->group(function () {
        Route::view('/', 'admin.dashboard')->name('dashboard');
        Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
        Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])
            ->whereNumber('document')
            ->name('documents.destroy');
        Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    });
});

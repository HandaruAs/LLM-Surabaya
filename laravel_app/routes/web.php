<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Auth\UserAuthController;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\UseJwtGuard;
use Illuminate\Support\Facades\Route;

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
        Route::view('/', 'admin.dashboard')->name('dashboard');
        Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
        Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])
            ->whereNumber('document')
            ->name('documents.destroy');
        Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    });
});

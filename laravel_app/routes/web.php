<?php

use Illuminate\Support\Facades\Route;

// Halaman chat. Semua data (kirim pesan, riwayat) lewat routes/api.php -> /api/...
Route::view('/', 'chat')->name('chat');

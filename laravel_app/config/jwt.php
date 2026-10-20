<?php

return [

    /*
    | Kunci rahasia untuk menandatangani JWT (HS256).
    | Kosongkan untuk memakai turunan dari APP_KEY. Bila diisi, pakai string
    | acak minimal 32 karakter. Mengganti kunci ini = semua user ter-logout.
    */
    'secret' => env('JWT_SECRET'),

    // Masa berlaku token (menit). "Ingat saya" memakai remember_ttl.
    'ttl' => (int) env('JWT_TTL', 120),
    'remember_ttl' => (int) env('JWT_REMEMBER_TTL', 60 * 24 * 30),

    // Toleransi selisih jam server (detik) saat mengecek exp/nbf.
    'leeway' => 30,

    // Nama cookie tempat token disimpan (httpOnly, tidak bisa dibaca JavaScript).
    'cookie' => env('JWT_COOKIE', 'llm_token'),

];

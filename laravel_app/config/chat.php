<?php

return [

    /*
    | Layanan RAG Python (internal, hanya di localhost). Lihat PRD 8.2.
    */
    'rag' => [
        'url' => env('RAG_SERVICE_URL', 'http://127.0.0.1:8001'),
        // Inferensi lokal di laptop 8 GB bisa lambat (PRD 11: ~120 detik).
        'timeout' => (int) env('RAG_TIMEOUT', 120),
    ],

    // Jumlah pesan terakhir yang dikirim sebagai konteks percakapan (FR-05).
    'history_limit' => (int) env('CHAT_HISTORY_LIMIT', 6),

    // Batas kirim pesan per menit (FR-16).
    'rate_limit' => [
        'per_user' => (int) env('CHAT_RATE_PER_USER', 10),  // user yang login, per akun
        'per_guest' => (int) env('CHAT_RATE_PER_GUEST', 10), // tamu, per IP
        'per_ip' => (int) env('CHAT_RATE_PER_IP', 30),       // total per IP (tamu + user)
    ],

];

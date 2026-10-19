<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Rag\RagClient;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * GET /api/health — dipakai UI untuk menampilkan status layanan AI.
     * Riwayat tetap bisa dibuka walau "ai" bernilai false.
     */
    public function __invoke(RagClient $rag): JsonResponse
    {
        $status = $rag->health();

        return response()->json([
            'api' => true,
            'ai' => $status !== null && ($status['ollama'] ?? true) === true,
            'rag' => $status,
        ]);
    }
}

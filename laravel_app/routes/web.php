<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::view('/', 'chat')->name('chat');

Route::post('/chat', function (Request $request) {
    $data = $request->validate([
        'message' => ['required', 'string', 'max:2000'],
        'history' => ['sometimes', 'array', 'max:20'],
        'lang' => ['sometimes', 'in:id,en'],
    ]);

    try {
        $res = Http::timeout(60)->acceptJson()->post(
            rtrim(config('services.llm.base_url', 'http://127.0.0.1:8001'), '/') . '/chat',
            [
                'question' => $data['message'],
                'history' => $data['history'] ?? [],
                'lang' => $data['lang'] ?? 'id',
            ]
        );

        if ($res->failed()) {
            return response()->json(['error' => 'Server LLM error (' . $res->status() . ').'], 502);
        }

        return response()->json([
            'answer' => (string) $res->json('answer', ''),
            'sources' => $res->json('sources', []),
            'suggestions' => $res->json('suggestions', []),
        ]);
    } catch (Throwable $e) {
        return response()->json(['error' => 'Tidak bisa terhubung ke server LLM.'], 503);
    }
})->name('chat.ask');
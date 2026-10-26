<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_chat_sends_saved_role_and_only_active_rules_to_rag(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ai-config.json', json_encode([
            'role' => 'Jadilah asisten keuangan Surabaya.',
            'rules' => [
                ['text' => 'Jangan mengarang angka.', 'on' => true],
                ['text' => 'Gunakan bahasa puitis.', 'on' => false],
            ],
            'top_k' => 3,
            'num_ctx' => 512,
            'show_context' => false,
            'think' => false,
        ]));
        config(['chat.rag.url' => 'http://rag.test']);
        Http::fake(['rag.test/chat' => Http::response(['answer' => 'Jawaban uji.', 'sources' => []])]);

        $user = User::factory()->create();
        $this->actingAs($user, 'jwt')->postJson('/api/chat', ['message' => 'Pertanyaan uji'])->assertOk();

        Http::assertSent(fn (Request $request) => $request->url() === 'http://rag.test/chat'
            && $request['question'] === 'Pertanyaan uji'
            && $request['system_prompt'] === "Jadilah asisten keuangan Surabaya.\n\nAturan jawaban:\n- Jangan mengarang angka."
            && $request['top_k'] === 3
            && $request['num_ctx'] === 512
            && $request['think'] === false
            && $request['show_context'] === false);
    }
}

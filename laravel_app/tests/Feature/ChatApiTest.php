<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\MessageSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatApiTest extends TestCase
{
    use RefreshDatabase;

    private string $visitor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->visitor = (string) Str::uuid();
        config(['chat.rag.url' => 'http://rag.test']);
    }

    private function headers(?string $visitor = null): array
    {
        return ['X-Visitor-Id' => $visitor ?? $this->visitor, 'Accept' => 'application/json'];
    }

    private function fakeRag(array $body = ['answer' => 'Dana darurat adalah uang cadangan.', 'sources' => ['dana-darurat.pdf']], int $status = 200): void
    {
        Http::fake(['rag.test/chat' => Http::response($body, $status)]);
    }

    public function test_admin_document_list_requires_authentication(): void
    {
        $this->get('/admin/documents')->assertRedirect('/admin/login');
    }

    public function test_admin_login_and_dashboard_require_authentication(): void
    {
        $this->get('/admin/login')->assertOk();
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->post('/register')->assertNotFound();
    }

    public function test_admin_can_log_in_and_log_out(): void
    {
        $admin = \App\Models\User::factory()->create(['password' => 'CorrectHorseBattery1!']);

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'CorrectHorseBattery1!'])
            ->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin);
        $this->get('/admin')->assertOk();

        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_invalid_admin_credentials_do_not_authenticate(): void
    {
        $admin = \App\Models\User::factory()->create();

        $this->from('/admin/login')
            ->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong'])
            ->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_admin_login_is_rate_limited(): void
    {
        $admin = \App\Models\User::factory()->create();
        foreach (range(1, 6) as $_) {
            $this->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong']);
        }
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong'])
            ->assertTooManyRequests();
    }

    public function test_public_registration_is_disabled(): void
    {
        $this->post('/register')->assertNotFound();
    }

    public function test_legacy_web_chat_endpoint_is_not_registered(): void
    {
        $this->postJson('/chat', ['message' => 'test'])
            ->assertNotFound();
    }

    public function test_send_message_creates_session_and_returns_answer(): void
    {
        $this->fakeRag();

        $response = $this->postJson('/api/chat', ['message' => 'Apa itu dana darurat?'], $this->headers());

        $response->assertOk()
            ->assertJsonPath('title', 'Apa itu dana darurat?')
            ->assertJsonPath('message.role', 'assistant')
            ->assertJsonPath('message.content', 'Dana darurat adalah uang cadangan.')
            ->assertJsonPath('message.sources', ['dana-darurat.pdf'])
            ->assertJsonStructure(['session_id', 'title', 'message' => ['id', 'role', 'content', 'sources', 'created_at']]);

        $session = ChatSession::findOrFail($response->json('session_id'));
        $this->assertSame($this->visitor, $session->visitor_id);
        $this->assertSame(['user', 'assistant'], $session->messages()->orderBy('id')->pluck('role')->all());
        $this->assertNotNull($session->messages()->where('role', 'assistant')->first()->response_ms);
    }

    public function test_title_is_first_40_characters_of_question(): void
    {
        $this->fakeRag();
        $question = 'Bagaimana cara membuat anggaran bulanan yang baik untuk keluarga?';

        $this->postJson('/api/chat', ['message' => $question], $this->headers())
            ->assertOk()
            ->assertJsonPath('title', rtrim(mb_substr($question, 0, 40)));
    }

    public function test_history_sends_last_six_previous_messages_without_current_question(): void
    {
        $this->fakeRag();
        $session = ChatSession::create(['visitor_id' => $this->visitor, 'title' => 'Lama']);
        foreach (range(1, 8) as $i) {
            $session->messages()->create(['role' => $i % 2 ? 'user' : 'assistant', 'content' => "pesan $i"]);
        }

        $this->postJson('/api/chat', ['session_id' => $session->id, 'message' => 'pertanyaan baru'], $this->headers())
            ->assertOk()
            ->assertJsonPath('session_id', $session->id);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'http://rag.test/chat'
                && $request['question'] === 'pertanyaan baru'
                && array_column($request['history'], 'content') === ['pesan 3', 'pesan 4', 'pesan 5', 'pesan 6', 'pesan 7', 'pesan 8'];
        });
    }

    public function test_detailed_sources_from_db_py_are_saved_as_snapshots(): void
    {
        $this->fakeRag(['answer' => 'Jawaban', 'sources' => [
            ['id' => 7, 'filename' => 'b.pdf', 'content' => 'potongan b', 'similarity' => 0.81],
            ['id' => 3, 'filename' => 'a.txt', 'content' => 'potongan a', 'similarity' => 0.75],
            ['id' => 9, 'filename' => 'b.pdf', 'content' => 'potongan b2', 'similarity' => 0.70],
        ]]);

        $response = $this->postJson('/api/chat', ['message' => 'tanya'], $this->headers())
            ->assertOk()
            ->assertJsonPath('message.sources', ['a.txt', 'b.pdf']);

        $message = ChatMessage::findOrFail($response->json('message.id'));
        $this->assertCount(3, $message->sourceChunks);
        // Potongan dengan id yang tidak ada di rag_chunks disimpan dengan chunk_id null (FK).
        $this->assertSame([null, null, null], $message->sourceChunks->pluck('chunk_id')->all());
        $this->assertSame('potongan b', $message->sourceChunks->first()->chunk_snapshot);
    }

    public function test_existing_chunk_id_is_linked_and_duplicates_are_skipped(): void
    {
        $docId = DB::table('rag_documents')->insertGetId(['filename' => 'dana-darurat.pdf']);
        $chunkId = DB::table('rag_chunks')->insertGetId([
            'document_id' => $docId, 'chunk_index' => 0, 'content' => 'isi',
            'embedding' => '['.implode(',', array_fill(0, 384, 0)).']',
        ]);
        $this->fakeRag(['answer' => 'Jawaban', 'sources' => [
            ['id' => $chunkId, 'filename' => 'dana-darurat.pdf', 'content' => 'isi', 'similarity' => 0.9],
            ['id' => $chunkId, 'filename' => 'dana-darurat.pdf', 'content' => 'isi', 'similarity' => 0.9],
        ]]);

        $id = $this->postJson('/api/chat', ['message' => 'tanya'], $this->headers())->assertOk()->json('message.id');

        $this->assertSame([$chunkId], MessageSource::where('message_id', $id)->pluck('chunk_id')->all());

        // Reindex di sisi Python menghapus potongan → riwayat tetap ada, chunk_id jadi null.
        DB::table('rag_documents')->where('id', $docId)->delete();
        $this->assertSame([null], MessageSource::where('message_id', $id)->pluck('chunk_id')->all());
    }

    public function test_filename_only_sources_are_not_written_to_message_sources(): void
    {
        $this->fakeRag();

        $this->postJson('/api/chat', ['message' => 'tanya'], $this->headers())
            ->assertOk()->assertJsonPath('message.sources', ['dana-darurat.pdf']);

        $this->assertSame(0, MessageSource::count());
    }

    public function test_db_py_insert_still_works_and_its_sessions_are_hidden_from_web(): void
    {
        // Sama persis dengan create_chat_session() di python_app/db.py.
        $id = DB::selectOne('INSERT INTO chat_sessions DEFAULT VALUES RETURNING id')->id;

        $this->getJson('/api/sessions', $this->headers())->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/sessions/{$id}/messages", $this->headers())->assertNotFound();
    }

    public function test_rag_down_returns_503_keeps_user_message_and_saves_no_answer(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $response = $this->postJson('/api/chat', ['message' => 'Apa itu dana darurat?'], $this->headers());

        $response->assertStatus(503)->assertJsonStructure(['message', 'session_id', 'title']);
        $session = ChatSession::findOrFail($response->json('session_id'));
        $this->assertSame(['user'], $session->messages()->pluck('role')->all());
    }

    public function test_rag_error_or_empty_answer_returns_503(): void
    {
        $this->fakeRag(['detail' => 'Ollama mati'], 500);
        $this->postJson('/api/chat', ['message' => 'a'], $this->headers())->assertStatus(503);

        $this->fakeRag(['answer' => '   ', 'sources' => []]);
        $this->postJson('/api/chat', ['message' => 'b'], $this->headers())->assertStatus(503);

        $this->assertSame(0, ChatMessage::where('role', 'assistant')->count());
    }

    public function test_validation_empty_and_too_long_message(): void
    {
        $this->fakeRag();

        $this->postJson('/api/chat', ['message' => '   '], $this->headers())
            ->assertStatus(422)->assertJsonValidationErrors('message');

        $this->postJson('/api/chat', ['message' => str_repeat('a', 1001)], $this->headers())
            ->assertStatus(422)->assertJsonValidationErrors('message');

        $this->postJson('/api/chat', ['message' => str_repeat('a', 1000)], $this->headers())->assertOk();

        $this->postJson('/api/chat', ['session_id' => 'abc', 'message' => 'hai'], $this->headers())
            ->assertStatus(422)->assertJsonValidationErrors('session_id');

        Http::assertSentCount(1);
    }

    public function test_missing_or_invalid_visitor_id_is_rejected(): void
    {
        $this->postJson('/api/chat', ['message' => 'hai'])->assertStatus(422);
        $this->getJson('/api/sessions', ['X-Visitor-Id' => 'abc'])->assertStatus(422);
    }

    public function test_sessions_of_other_visitor_are_not_accessible(): void
    {
        $this->fakeRag();
        $other = ChatSession::create(['visitor_id' => (string) Str::uuid(), 'title' => 'Milik orang lain']);

        $this->getJson("/api/sessions/{$other->id}/messages", $this->headers())->assertNotFound();
        $this->patchJson("/api/sessions/{$other->id}", ['title' => 'x'], $this->headers())->assertNotFound();
        $this->deleteJson("/api/sessions/{$other->id}", [], $this->headers())->assertNotFound();
        $this->postJson('/api/chat', ['session_id' => $other->id, 'message' => 'hai'], $this->headers())->assertNotFound();
        $this->getJson('/api/sessions', $this->headers())->assertOk()->assertJsonCount(0, 'data');

        $this->assertDatabaseHas('chat_sessions', ['id' => $other->id, 'title' => 'Milik orang lain']);
        $this->assertSame(0, ChatMessage::count());
    }

    public function test_non_numeric_session_id_in_url_returns_404(): void
    {
        $this->getJson('/api/sessions/abc/messages', $this->headers())->assertNotFound();
        $this->getJson('/api/sessions/999999/messages', $this->headers())->assertNotFound();
        // Melebihi bigint: 404, bukan error 500.
        $this->getJson('/api/sessions/99999999999999999999/messages', $this->headers())->assertNotFound();
        $this->getJson('/api/sessions/0/messages', $this->headers())->assertNotFound();
    }

    public function test_list_sessions_newest_first_and_messages_oldest_first(): void
    {
        $old = ChatSession::create(['visitor_id' => $this->visitor, 'title' => 'Lama']);
        $old->forceFill(['updated_at' => now()->subDay()])->saveQuietly();
        $new = ChatSession::create(['visitor_id' => $this->visitor, 'title' => 'Baru']);
        $new->messages()->create(['role' => 'user', 'content' => 'pertama']);
        $new->messages()->create(['role' => 'assistant', 'content' => 'kedua', 'sources' => ['a.txt']]);

        $this->getJson('/api/sessions', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.0.id', $new->id)
            ->assertJsonPath('data.1.id', $old->id)
            ->assertJsonMissingPath('data.0.visitor_id');

        $this->getJson("/api/sessions/{$new->id}/messages", $this->headers())
            ->assertOk()
            ->assertJsonPath('data.0.content', 'pertama')
            ->assertJsonPath('data.1.content', 'kedua')
            ->assertJsonPath('data.1.sources', ['a.txt'])
            ->assertJsonPath('data.0.sources', []);
    }

    public function test_rename_and_delete_session(): void
    {
        $session = ChatSession::create(['visitor_id' => $this->visitor, 'title' => 'Lama']);
        $session->messages()->create(['role' => 'user', 'content' => 'hai']);

        $this->patchJson("/api/sessions/{$session->id}", ['title' => 'Judul baru'], $this->headers())
            ->assertOk()->assertJsonPath('title', 'Judul baru');
        $this->patchJson("/api/sessions/{$session->id}", ['title' => ''], $this->headers())
            ->assertStatus(422);

        $this->deleteJson("/api/sessions/{$session->id}", [], $this->headers())->assertNoContent();
        $this->assertDatabaseMissing('chat_sessions', ['id' => $session->id]);
        $this->assertSame(0, ChatMessage::count());
    }

    public function test_rate_limit_per_visitor(): void
    {
        config(['chat.rate_limit.per_visitor' => 2]);
        $this->fakeRag();

        $this->postJson('/api/chat', ['message' => '1'], $this->headers())->assertOk();
        $this->postJson('/api/chat', ['message' => '2'], $this->headers())->assertOk();
        $this->postJson('/api/chat', ['message' => '3'], $this->headers())->assertStatus(429)->assertJsonStructure(['message']);

        // Pengunjung lain belum kena batas.
        $this->postJson('/api/chat', ['message' => '4'], $this->headers((string) Str::uuid()))->assertOk();
    }

    public function test_rate_limit_per_ip(): void
    {
        config(['chat.rate_limit.per_ip' => 2]);
        $this->fakeRag();

        $this->postJson('/api/chat', ['message' => '1'], $this->headers((string) Str::uuid()))->assertOk();
        $this->postJson('/api/chat', ['message' => '2'], $this->headers((string) Str::uuid()))->assertOk();
        $this->postJson('/api/chat', ['message' => '3'], $this->headers((string) Str::uuid()))->assertStatus(429);
    }

    public function test_delete_all_removes_only_own_sessions(): void
    {
        foreach (['a', 'b'] as $title) {
            ChatSession::create(['visitor_id' => $this->visitor, 'title' => $title])
                ->messages()->create(['role' => 'user', 'content' => 'hai']);
        }
        $other = ChatSession::create(['visitor_id' => (string) Str::uuid(), 'title' => 'orang lain']);

        $this->deleteJson('/api/sessions', [], $this->headers())->assertNoContent();

        $this->getJson('/api/sessions', $this->headers())->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseHas('chat_sessions', ['id' => $other->id]);
        $this->assertSame(0, ChatMessage::count());
    }

    public function test_retry_answers_last_unanswered_question_without_saving_it_again(): void
    {
        $aiOnline = false;
        Http::fake(function () use (&$aiOnline) {
            if (! $aiOnline) {
                throw new ConnectionException('down');
            }

            return Http::response(['answer' => 'Dana darurat adalah uang cadangan.', 'sources' => ['dana-darurat.pdf']]);
        });

        // Percobaan pertama gagal: pertanyaan tersimpan, jawaban tidak.
        $sessionId = $this->postJson('/api/chat', ['message' => 'Apa itu dana darurat?'], $this->headers())
            ->assertStatus(503)
            ->json('session_id');

        // Layanan AI hidup lagi, pengguna menekan "Kirim ulang".
        $aiOnline = true;
        $this->postJson("/api/sessions/{$sessionId}/retry", [], $this->headers())
            ->assertOk()
            ->assertJsonPath('session_id', $sessionId)
            ->assertJsonPath('message.role', 'assistant')
            ->assertJsonPath('message.content', 'Dana darurat adalah uang cadangan.');

        Http::assertSent(fn (Request $r) => $r['question'] === 'Apa itu dana darurat?' && $r['history'] === []);

        $session = ChatSession::findOrFail($sessionId);
        $this->assertSame(['user', 'assistant'], $session->messages()->orderBy('id')->pluck('role')->all());
    }

    public function test_retry_still_failing_returns_503_and_saves_nothing(): void
    {
        $session = ChatSession::create(['visitor_id' => $this->visitor, 'title' => 'x']);
        $session->messages()->create(['role' => 'user', 'content' => 'tanya']);
        Http::fake(fn () => throw new ConnectionException('down'));

        $this->postJson("/api/sessions/{$session->id}/retry", [], $this->headers())
            ->assertStatus(503)
            ->assertJsonPath('session_id', $session->id);

        $this->assertSame(1, $session->messages()->count());
    }

    public function test_retry_when_last_message_already_answered_returns_422(): void
    {
        $this->fakeRag();
        $empty = ChatSession::create(['visitor_id' => $this->visitor, 'title' => 'kosong']);
        $answered = ChatSession::create(['visitor_id' => $this->visitor, 'title' => 'x']);
        $answered->messages()->create(['role' => 'user', 'content' => 'tanya']);
        $answered->messages()->create(['role' => 'assistant', 'content' => 'jawab']);

        $this->postJson("/api/sessions/{$answered->id}/retry", [], $this->headers())
            ->assertStatus(422)->assertJsonStructure(['message']);
        $this->postJson("/api/sessions/{$empty->id}/retry", [], $this->headers())->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_retry_on_other_visitors_session_returns_404(): void
    {
        $this->fakeRag();
        $other = ChatSession::create(['visitor_id' => (string) Str::uuid(), 'title' => 'x']);
        $other->messages()->create(['role' => 'user', 'content' => 'tanya']);

        $this->postJson("/api/sessions/{$other->id}/retry", [], $this->headers())->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_retry_is_blocked_while_same_session_is_processing(): void
    {
        $this->fakeRag();
        $session = ChatSession::create(['visitor_id' => $this->visitor, 'title' => 'x']);
        $session->messages()->create(['role' => 'user', 'content' => 'tanya']);

        $lock = \Illuminate\Support\Facades\Cache::lock("chat-retry:{$session->id}", 60);
        $lock->get();

        $this->postJson("/api/sessions/{$session->id}/retry", [], $this->headers())->assertStatus(409);
        Http::assertNothingSent();

        $lock->release();
    }

    public function test_history_still_readable_when_rag_is_down(): void
    {
        Http::fake(fn () => throw new ConnectionException('down'));
        $session = ChatSession::create(['visitor_id' => $this->visitor, 'title' => 'Lama']);
        $session->messages()->create(['role' => 'user', 'content' => 'hai']);

        $this->getJson('/api/sessions', $this->headers())->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/sessions/{$session->id}/messages", $this->headers())->assertOk();
        $this->getJson('/api/health')->assertOk()->assertJsonPath('ai', false);
    }
}

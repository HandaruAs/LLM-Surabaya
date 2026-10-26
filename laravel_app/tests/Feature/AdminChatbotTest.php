<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminChatbotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['chat.rag.url' => 'http://rag.test']);
        Storage::fake('local');
        Http::fake(['rag.test/chat' => Http::response(['answer' => 'Halo admin.', 'sources' => []])]);
    }

    public function test_admin_navigation_chatbot_link_opens_admin_chat_not_public_chat(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'web')
            ->get('/admin/documents')
            ->assertOk()
            ->assertSee(route('admin.ai-chat'), false)
            ->assertDontSee('href="' . route('chat') . '"', false);
    }

    public function test_admin_chatbot_page_greets_admin_and_hides_user_auth_buttons(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin Surabaya']);

        $this->actingAs($admin, 'web')
            ->get('/admin/ai-chat')
            ->assertOk()
            ->assertSee('Admin Surabaya')
            ->assertSee('&quot;loggedIn&quot;:true', false)
            ->assertSee('&quot;isAdmin&quot;:true', false)
            ->assertSee('&quot;api&quot;:&quot;http://localhost/admin/ai-chat&quot;', false)
            ->assertSee('&quot;logout&quot;:&quot;http://localhost/admin/logout&quot;', false)
            ->assertSee('&quot;admin&quot;:&quot;http://localhost/admin&quot;', false)
            ->assertDontSee('>Masuk</a>', false)
            ->assertDontSee('>Daftar</a>', false);
    }

    public function test_admin_chatbot_saves_messages_to_admin_owned_history(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'web')
            ->postJson('/admin/ai-chat/messages', ['message' => 'Tanya untuk admin'])
            ->assertOk()
            ->assertJsonPath('message.content', 'Halo admin.');

        $session = ChatSession::findOrFail($response->json('session_id'));
        $this->assertSame($admin->id, $session->user_id);
        $this->assertSame(['user', 'assistant'], $session->messages()->orderBy('id')->pluck('role')->all());
        $this->assertDatabaseHas('chat_messages', ['session_id' => $session->id, 'role' => 'user', 'content' => 'Tanya untuk admin']);
    }

    public function test_guest_cannot_use_admin_chatbot_endpoints(): void
    {
        $this->get('/admin/ai-chat')->assertRedirect('/login');
        $this->postJson('/admin/ai-chat/messages', ['message' => 'Tanya'])->assertUnauthorized();
    }
}

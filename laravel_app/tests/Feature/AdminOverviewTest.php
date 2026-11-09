<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['chat.rag.url' => 'http://rag.test']);
        Http::fake(['rag.test/health' => Http::response(['status' => 'ok'])]);
    }

    public function test_admin_sees_overview_with_stats(): void
    {
        $user = User::factory()->create();
        $session = $user->chatSessions()->create(['title' => 'Pajak']);
        $session->messages()->create(['role' => 'user', 'content' => 'Apa itu pajak?']);
        $session->messages()->create(['role' => 'assistant', 'content' => 'Pajak adalah ...', 'response_ms' => 4000]);
        DB::table('admin_documents')->insert([
            'original_name' => 'apbd.pdf', 'stored_path' => 'admin-documents/a.pdf', 'mime_type' => 'application/pdf',
            'size_bytes' => 1000, 'status' => 'indexed', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs(User::factory()->admin()->create(), 'web')
            ->get('/admin/overview')
            ->assertOk()
            ->assertSee('Ringkasan')
            ->assertSee('data-react="overview"', false)
            ->assertSee('&quot;questions&quot;:1', false)
            ->assertSee('&quot;indexed&quot;:1', false)
            ->assertSee('&quot;avg_response_ms&quot;:4000', false)
            ->assertSee('&quot;ai_online&quot;:true', false)
            // Isi pertanyaan pengguna tidak ikut ditampilkan di ringkasan.
            ->assertDontSee('Apa itu pajak?');
    }

    public function test_overview_is_only_for_admins(): void
    {
        $this->get('/admin/overview')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(), 'web')->get('/admin/overview')->assertForbidden();
    }

    public function test_admin_menu_links_to_overview(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'web')
            ->get('/admin/documents')
            ->assertOk()
            ->assertSee(route('admin.overview'), false);
    }
}

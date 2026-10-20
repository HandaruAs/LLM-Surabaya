<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentIndexBridgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_upload_forwards_file_to_rag_and_records_indexed_status(): void
    {
        Http::fake(['rag.test/documents/index' => Http::response(['status' => 'indexed', 'chunks' => 2])]);
        config(['services.rag.url' => 'https://rag.test']);

        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/documents', [
                'document' => UploadedFile::fake()->create('laporan.pdf', 10, 'application/pdf'),
            ])
            ->assertRedirect('/admin/documents')
            ->assertSessionHas('status');

        $this->assertDatabaseHas('admin_documents', [
            'original_name' => 'laporan.pdf',
            'status' => 'indexed',
        ]);
        Http::assertSent(fn ($request) => $request->url() === 'https://rag.test/documents/index');
    }

    public function test_upload_remains_saved_and_shows_failed_status_when_rag_is_unavailable(): void
    {
        Http::fake(['rag.test/documents/index' => Http::response([], 503)]);
        config(['services.rag.url' => 'https://rag.test']);

        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/documents', [
                'document' => UploadedFile::fake()->create('laporan.pdf', 10, 'application/pdf'),
            ])
            ->assertRedirect('/admin/documents')
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('admin_documents', [
            'original_name' => 'laporan.pdf',
            'status' => 'failed',
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminDocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Http::fake([
            '*' => Http::response(['status' => 'indexed', 'chunks' => 1]),
        ]);
    }

    public function test_admin_can_view_document_management_page(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/documents')
            ->assertOk()
            ->assertSee('Manajemen dokumen');
    }

    public function test_guest_cannot_upload_documents(): void
    {
        $this->post('/admin/documents', [
            'document' => UploadedFile::fake()->create('laporan.pdf', 10, 'application/pdf'),
        ])->assertRedirect('/login');
    }

    public function test_admin_can_upload_pdf_and_it_is_listed_as_indexed(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/documents', [
                'document' => UploadedFile::fake()->create('laporan.pdf', 10, 'application/pdf'),
            ])
            ->assertRedirect('/admin/documents');

        $this->assertDatabaseHas('admin_documents', [
            'original_name' => 'laporan.pdf',
            'status' => 'indexed',
        ]);
        $storedPath = \Illuminate\Support\Facades\DB::table('admin_documents')->where('original_name', 'laporan.pdf')->value('stored_path');
        Storage::disk('local')->assertExists($storedPath);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/documents')
            ->assertOk()
            ->assertSee('laporan.pdf')
            ->assertSee('Terindeks');
    }

    public function test_admin_can_upload_xlsx_file(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/documents', [
                'document' => UploadedFile::fake()->create(
                    'laporan.xlsx', 10,
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                ),
            ])
            ->assertRedirect('/admin/documents');

        $this->assertDatabaseHas('admin_documents', ['original_name' => 'laporan.xlsx']);
    }

    public function test_admin_cannot_upload_unsupported_document_format(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->from('/admin/documents')
            ->post('/admin/documents', [
                'document' => UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream'),
            ])
            ->assertSessionHasErrors('document');

        $this->assertDatabaseCount('admin_documents', 0);
    }

    public function test_admin_can_delete_an_uploaded_document(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/documents', [
                'document' => UploadedFile::fake()->create('hapus.pdf', 10, 'application/pdf'),
            ]);

        $documentId = \Illuminate\Support\Facades\DB::table('admin_documents')->value('id');

        $this->actingAs(User::factory()->admin()->create())
            ->delete('/admin/documents/'.$documentId)
            ->assertRedirect('/admin/documents');

        $this->assertDatabaseCount('admin_documents', 0);

    }
}

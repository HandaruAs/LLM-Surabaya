<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Services\RagDocumentIndexer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class DocumentController extends Controller
{
    public function index(): View
    {
        $documents = DB::table('admin_documents')->orderByDesc('created_at')->get();
        foreach ($documents as $document) {
            $document->status_label = match ($document->status) {
                'indexed' => 'Terindeks',
                'failed' => 'Gagal indeks',
                default => 'Menunggu indeks',
            };
        }

        return view('admin.documents.index', compact('documents'));
    }

    public function store(Request $request, RagDocumentIndexer $indexer): RedirectResponse
    {
        $validated = $request->validate([
            'document' => ['required', 'file', 'max:51200', 'mimes:pdf,xlsx'],
        ]);

        $file = $validated['document'];
        $originalName = basename($file->getClientOriginalName());
        $storedPath = $file->store('admin-documents');

        if ($storedPath === false) {
            throw ValidationException::withMessages(['document' => 'Dokumen gagal disimpan.']);
        }

        try {
            DB::table('admin_documents')->insert([
                'original_name' => $originalName,
                'stored_path' => $storedPath,
                'mime_type' => (string) $file->getMimeType(),
                'size_bytes' => (int) $file->getSize(),
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Storage::disk(config('filesystems.default'))->delete($storedPath);
            throw $exception;
        }

        $documentId = (int) DB::table('admin_documents')->where('stored_path', $storedPath)->value('id');

        try {
            $chunks = $indexer->index($documentId, $storedPath, $originalName);
            DB::table('admin_documents')->where('id', $documentId)->update([
                'status' => 'indexed',
                'updated_at' => now(),
            ]);

            return redirect()->route('admin.documents.index')
                ->with('status', "Dokumen berhasil diindeks ($chunks potongan). ");
        } catch (Throwable $exception) {
            DB::table('admin_documents')->where('id', $documentId)->update([
                'status' => 'failed',
                'updated_at' => now(),
            ]);
            Log::warning('Indeks dokumen gagal', ['document_id' => $documentId, 'error' => $exception->getMessage()]);

            return redirect()->route('admin.documents.index')
                ->with('warning', 'Berkas tersimpan, tetapi gagal diindeks. Periksa layanan Python RAG dan coba lagi.');
        }
    }

    public function destroy(int $document, RagDocumentIndexer $indexer): RedirectResponse
    {
        $record = DB::table('admin_documents')->where('id', $document)->first();
        abort_if($record === null, 404);

        try {
            $indexer->delete($document);
        } catch (Throwable $exception) {
            Log::warning('Penghapusan indeks dokumen gagal', ['document_id' => $document, 'error' => $exception->getMessage()]);

            return redirect()->route('admin.documents.index')
                ->with('warning', 'Indeks dokumen belum terhapus. Coba lagi saat layanan Python RAG tersedia.');
        }

        DB::table('admin_documents')->where('id', $document)->delete();
        Storage::disk(config('filesystems.default'))->delete($record->stored_path);

        return redirect()->route('admin.documents.index')->with('status', 'Dokumen berhasil dihapus.');
    }
}

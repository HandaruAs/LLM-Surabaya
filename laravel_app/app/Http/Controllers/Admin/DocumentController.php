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
        $fileInput = $request->file('document');
        $isMultiple = is_array($fileInput);
        $validationData = ['document' => $fileInput];
        $rules = $isMultiple
            ? ['document' => ['required', 'array', 'min:1', 'max:20'], 'document.*' => ['required', 'file', 'max:51200', 'mimes:pdf,xlsx']]
            : ['document' => ['required', 'file', 'max:51200', 'mimes:pdf,xlsx']];
        $validated = validator($validationData, $rules)->validate();
        $files = $isMultiple ? $validated['document'] : [$validated['document']];

        $indexed = 0;
        $failed = 0;

        foreach ($files as $file) {
            $originalName = basename($file->getClientOriginalName());
            $storedPath = $file->store('admin-documents');

            if ($storedPath === false) {
                $failed++;
                continue;
            }

            // Same-name uploads replace the existing document, as before.
            $existingDoc = DB::table('admin_documents')->where('original_name', $originalName)->first();
            if ($existingDoc !== null) {
                try {
                    $indexer->delete($existingDoc->id);
                } catch (Throwable $exception) {
                    Log::warning('Penghapusan indeks dokumen lama gagal', ['document_id' => $existingDoc->id, 'error' => $exception->getMessage()]);
                }
                Storage::disk(config('filesystems.default'))->delete($existingDoc->stored_path);
                $documentId = $existingDoc->id;
                DB::table('admin_documents')->where('id', $documentId)->update([
                    'stored_path' => $storedPath,
                    'mime_type' => (string) $file->getMimeType(),
                    'size_bytes' => (int) $file->getSize(),
                    'status' => 'pending',
                    'updated_at' => now(),
                ]);
            } else {
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
            }

            try {
                $chunks = $indexer->index($documentId, $storedPath, $originalName);
                DB::table('admin_documents')->where('id', $documentId)->update([
                    'status' => 'indexed',
                    'updated_at' => now(),
                ]);
                $indexed++;
            } catch (Throwable $exception) {
                DB::table('admin_documents')->where('id', $documentId)->update([
                    'status' => 'failed',
                    'updated_at' => now(),
                ]);
                Log::warning('Indeks dokumen gagal', ['document_id' => $documentId, 'error' => $exception->getMessage()]);
                $failed++;
            }
        }

        if ($failed > 0) {
            return redirect()->route('admin.documents.index')
                ->with('warning', "$indexed dokumen berhasil diindeks; $failed gagal. Periksa layanan Python RAG dan status dokumen.");
        }

        return redirect()->route('admin.documents.index')
            ->with('status', "$indexed dokumen berhasil diindeks.");
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

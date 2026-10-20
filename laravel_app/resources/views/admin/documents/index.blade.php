<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manajemen dokumen - {{ config('app.name', 'LLM Surabaya') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-paper px-4 py-10 text-ink">
    <main class="mx-auto max-w-4xl space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-line bg-card p-6">
            <div>
                <a href="{{ route('admin.dashboard') }}" class="text-sm text-brand hover:underline">← Area Admin</a>
                <h1 class="mt-2 font-serif text-2xl font-semibold">Manajemen dokumen</h1>
                <p class="mt-1 text-sm text-mute">Unggah dokumen PDF atau Excel (.xlsx), maksimal 50 MB.</p>
            </div>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button class="rounded-lg border border-line px-4 py-2 text-sm hover:bg-tint">Keluar</button>
            </form>
        </header>

        @if (session('status'))
            <p role="status" class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</p>
            @endif
            @if (session('warning'))
            <p role="alert" class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ session('warning') }}</p>
            @endif

        <section class="rounded-2xl border border-line bg-card p-6">
            <h2 class="font-serif text-lg font-semibold">Unggah dokumen</h2>
            <form method="POST" action="{{ route('admin.documents.store') }}" enctype="multipart/form-data" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                @csrf
                <label class="flex flex-1 flex-col gap-1 text-sm font-medium">
                    Pilih file PDF atau XLSX
                    <input type="file" name="document" accept=".pdf,.xlsx,application/pdf,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required class="rounded-lg border border-line bg-paper px-3 py-2">
                </label>
                <button type="submit" class="rounded-lg bg-brand px-5 py-2.5 font-medium text-paper hover:opacity-90">Unggah</button>
            </form>
            @error('document')
                <p role="alert" class="mt-3 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </section>

        <section class="rounded-2xl border border-line bg-card p-6">
            <h2 class="font-serif text-lg font-semibold">Dokumen tersimpan</h2>
            @if ($documents->isEmpty())
                <p class="mt-4 text-sm text-mute">Belum ada dokumen yang diunggah.</p>
            @else
                <ul class="mt-4 divide-y divide-line">
                    @foreach ($documents as $document)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-4">
                            <div class="min-w-0">
                                <p class="break-all font-medium">{{ $document->original_name }}</p>
                                <p class="mt-1 text-xs text-mute">{{ strtoupper(pathinfo($document->original_name, PATHINFO_EXTENSION)) }} · {{ number_format($document->size_bytes / 1024, 1) }} KB · {{ $document->status_label }}</p>
                            </div>
                            <form method="POST" action="{{ route('admin.documents.destroy', $document->id) }}" data-confirm="Hapus dokumen {{ $document->original_name }}?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-lg border border-red-200 px-3 py-2 text-sm text-red-700 hover:bg-red-50">Hapus</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </main>
    {{-- Konfirmasi hapus. Bukan atribut onsubmit karena CSP memblokir handler inline. --}}
    <script nonce="{{ Vite::cspNonce() }}">
        document.querySelectorAll('form[data-confirm]').forEach(form => {
            form.addEventListener('submit', e => { if (!confirm(form.dataset.confirm)) e.preventDefault(); });
        });
    </script>
</body>
</html>

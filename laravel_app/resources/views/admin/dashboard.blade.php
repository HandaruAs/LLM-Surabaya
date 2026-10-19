<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin - {{ config('app.name', 'LLM Surabaya') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-paper px-4 py-12 text-ink">
    <main class="mx-auto max-w-3xl rounded-2xl border border-line bg-card p-8 shadow-sm">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="font-serif text-2xl font-semibold">Area Admin</h1>
                <p class="mt-2 text-sm text-mute">Login berhasil sebagai {{ auth()->user()->email }}.</p>
            </div>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="rounded-lg border border-line px-4 py-2 text-sm font-medium hover:bg-tint">
                    Keluar
                </button>
            </form>
        </div>
        <a href="{{ route('admin.documents.index') }}" class="mt-6 block rounded-lg border border-line px-4 py-3 text-sm font-medium hover:bg-tint">
            Buka manajemen dokumen →
        </a>
    </main>
</body>
</html>

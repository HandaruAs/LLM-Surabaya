<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Admin - {{ config('app.name', 'LLM Surabaya') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-paper px-4 py-12 text-ink">
    <main class="mx-auto max-w-md rounded-2xl border border-line bg-card p-8 shadow-sm">
        <h1 class="font-serif text-2xl font-semibold">Login Admin</h1>
        <p class="mt-2 text-sm text-mute">Masuk untuk mengelola dokumen dan konten.</p>

        @if ($errors->any())
            <div role="alert" class="mt-5 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.login.store') }}" class="mt-6 flex flex-col gap-4">
            @csrf
            <label class="flex flex-col gap-1 text-sm font-medium">
                Email
                <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                       class="rounded-lg border border-line bg-paper px-3 py-2">
            </label>
            <label class="flex flex-col gap-1 text-sm font-medium">
                Kata sandi
                <input type="password" name="password" required autocomplete="current-password"
                       class="rounded-lg border border-line bg-paper px-3 py-2">
            </label>
            <button type="submit" class="rounded-lg bg-brand px-4 py-2.5 font-medium text-paper hover:opacity-90">
                Masuk
            </button>
        </form>
    </main>
</body>
</html>

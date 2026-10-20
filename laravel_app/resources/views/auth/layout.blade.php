<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title') - {{ config('app.name', 'LLM Surabaya') }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;700&family=Source+Serif+4:wght@400;600&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-paper font-sans text-ink">
<main class="mx-auto flex min-h-dvh w-full max-w-md flex-col justify-center px-4 py-10">
  <a href="{{ route('chat') }}" class="mb-8 flex items-center gap-3 self-start">
    <span class="grid size-10 place-items-center rounded-xl bg-brand text-paper">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="size-5"><line x1="3" x2="21" y1="22" y2="22"/><line x1="6" x2="6" y1="18" y2="11"/><line x1="10" x2="10" y1="18" y2="11"/><line x1="14" x2="14" y1="18" y2="11"/><line x1="18" x2="18" y1="18" y2="11"/><polygon points="12 2 20 7 4 7"/></svg>
    </span>
    <span>
      <span class="block font-serif text-lg leading-tight font-semibold">LLM Surabaya</span>
      <span class="block text-xs text-mute">Informasi keuangan daerah</span>
    </span>
  </a>

  <div class="rounded-2xl border border-line bg-card p-6 shadow-sm sm:p-8">
    <h1 class="font-serif text-2xl font-semibold">@yield('heading')</h1>
    <p class="mt-1.5 text-sm text-mute">@yield('subheading')</p>
    <div class="mt-4 mb-6 h-[3px] w-12 bg-gold"></div>

    @if ($errors->any())
      <div role="alert" class="mb-5 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 dark:bg-red-950 dark:text-red-300">
        {{ $errors->first() }}
      </div>
    @endif

    @yield('form')
  </div>

  <p class="mt-6 text-center text-sm text-mute">@yield('footer')</p>
</main>
</body>
</html>

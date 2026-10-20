<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
@include('partials.head', ['title' => trim($__env->yieldContent('title'))])
</head>
<body class="min-h-full bg-paper font-sans text-ink">
@php
    $admin = auth('web')->user();
    $initials = collect(preg_split('/\s+/', trim($admin->name)))->filter()->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') ?: 'AD';
@endphp
<div class="grid min-h-dvh md:grid-cols-[17rem_minmax(0,1fr)]">

  <aside class="flex flex-col gap-6 border-b border-line bg-card px-4 py-5 md:sticky md:top-0 md:h-dvh md:border-r md:border-b-0">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <span class="grid size-10 place-items-center rounded-xl bg-brand text-paper shadow-sm"><x-icon name="landmark" class="size-5" /></span>
        <span class="font-serif text-lg font-semibold leading-tight">LLM Surabaya</span>
      </div>
      <span class="rounded-md bg-warning-bg px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-warning-ink">Admin</span>
    </div>

    {{-- Area admin hanya untuk kelola dokumen. --}}
    <nav class="flex flex-col gap-1">
      <div class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-mute">Menu</div>
      <a href="{{ route('admin.documents.index') }}" aria-current="page"
         class="flex items-center gap-3 rounded-xl bg-brand px-3 py-2.5 text-sm font-medium text-paper shadow-sm">
        <x-icon name="file" class="size-[18px]" />Kelola dokumen
      </a>
    </nav>

  </aside>

  <section class="flex min-w-0 flex-col">
    <header class="relative flex flex-col gap-4 border-b border-line bg-card px-4 py-5 sm:flex-row sm:items-start sm:justify-between sm:px-8">
      <div class="min-w-0 flex-1">
        <nav class="flex items-center gap-1.5 text-xs text-mute" aria-label="Breadcrumb">
          <span>Admin</span><x-icon name="chevron" class="size-3" /><span class="text-ink">@yield('title')</span>
        </nav>
        <h1 class="mt-1 font-serif text-2xl font-semibold">@yield('title')</h1>
        <p class="mt-0.5 text-sm text-mute">@yield('subheading')</p>
      </div>

      {{-- Akun admin + Keluar di kanan atas. --}}
      <div class="flex shrink-0 items-center gap-3">
        <div class="hidden items-center gap-2.5 sm:flex">
          <span class="grid size-8 shrink-0 place-items-center rounded-full bg-tint text-xs font-bold text-brand">{{ $initials }}</span>
          <div class="max-w-[14rem] leading-tight">
            <div class="truncate text-sm font-semibold">{{ $admin->name }}</div>
            <div class="truncate text-xs text-mute">{{ $admin->email }}</div>
          </div>
        </div>
        <form method="POST" action="{{ route('admin.logout') }}">
          @csrf
          <button type="submit" class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-line px-3 py-2 text-sm font-semibold text-mute transition hover:border-danger hover:text-danger-ink">
            <x-icon name="logout" class="size-4" />Keluar
          </button>
        </form>
      </div>
      <span class="absolute -bottom-px left-0 h-[3px] w-16 bg-gold"></span>
    </header>

    <div class="flex-1 space-y-6 px-4 py-6 sm:px-8">
      @yield('content')
    </div>
  </section>
</div>
@include('partials.dismiss-script')
@stack('scripts')
</body>
</html>

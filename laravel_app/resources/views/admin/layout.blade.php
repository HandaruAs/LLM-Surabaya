<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
@include('partials.head', ['title' => trim($__env->yieldContent('title'))])
@vite('resources/js/admin/main.js')
</head>
<body class="min-h-full bg-paper font-sans text-ink">
@php
    $admin = auth('web')->user();
    $initials = collect(preg_split('/\s+/', trim($admin->name)))->filter()->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') ?: 'AD';
    // Menu admin: [route, pola route aktif, label, ikon, keterangan singkat]
    $menu = [
        ['admin.documents.index', 'admin.documents.*', 'Kelola dokumen', 'file', 'Rujukan chatbot'],
        ['admin.ai-config', 'admin.ai-config', 'Konfigurasi AI', 'sliders', 'Peran & aturan jawaban'],
        ['admin.ai-chat', 'admin.ai-chat', 'Chatbot AI', 'bot', 'Uji coba jawaban'],
        ['admin.accounts.index', 'admin.accounts.*', 'Manajemen admin', 'users', 'Akun pengelola'],
    ];
@endphp
<div class="min-h-dvh md:grid md:grid-cols-[17.5rem_minmax(0,1fr)]">

  {{-- Bar atas khusus layar kecil --}}
  <div class="glass sticky top-0 z-30 flex items-center gap-3 border-b border-line px-4 py-3 md:hidden">
    <button type="button" data-nav-open aria-label="Buka menu" class="inline-grid size-10 cursor-pointer place-items-center rounded-xl text-mute transition hover:bg-tint hover:text-ink">
      <x-icon name="menu" class="size-5" />
    </button>
    <img src="{{ asset('images/lambang-surabaya.webp') }}" alt="" width="25" height="32" class="h-8 w-auto">
    <span class="flex-1 font-serif text-lg font-bold">Panel Admin</span>
    <button type="button" data-theme-toggle aria-label="Ganti tema" class="inline-grid size-10 cursor-pointer place-items-center rounded-xl text-mute transition hover:bg-tint hover:text-ink">
      <span data-theme-icon="light"><x-icon name="sun" class="size-[18px]" /></span>
      <span data-theme-icon="dark" class="hidden"><x-icon name="moon" class="size-[18px]" /></span>
      <span data-theme-icon="system" class="hidden"><x-icon name="monitor" class="size-[18px]" /></span>
    </button>
    <form method="POST" action="{{ route('admin.logout') }}">
      @csrf
      <button type="submit" title="Keluar" aria-label="Keluar" class="inline-grid size-10 cursor-pointer place-items-center rounded-xl text-mute transition hover:bg-danger-bg hover:text-danger-ink">
        <x-icon name="logout" class="size-[18px]" />
      </button>
    </form>
  </div>

  {{-- Latar gelap saat menu mobile terbuka --}}
  <div data-nav-scrim class="fixed inset-0 z-40 hidden bg-scrim backdrop-blur-[2px] md:hidden"></div>

  {{-- Sidebar (putih) --}}
  <aside data-nav class="fixed inset-y-0 left-0 z-50 flex w-[17.5rem] max-w-[85vw] -translate-x-full flex-col border-r border-line bg-card transition-transform duration-300 ease-out md:sticky md:top-0 md:h-dvh md:w-auto md:max-w-none md:translate-x-0">
    <div class="relative flex items-center gap-3 px-5 pt-6 pb-5">
      <span class="grid size-11 place-items-center rounded-2xl bg-tint ring-1 ring-line">
        <img src="{{ asset('images/lambang-surabaya.webp') }}" alt="Lambang Kota Surabaya" width="25" height="32" class="h-8 w-auto">
      </span>
      <div class="min-w-0 flex-1">
        <div class="truncate font-serif text-lg font-bold leading-tight">LLM Surabaya</div>
        <span class="mt-0.5 inline-flex items-center gap-1 rounded-md bg-warning-bg px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-warning-ink">
          <x-icon name="shield" class="size-3" />Panel Admin
        </span>
      </div>
      <button type="button" data-nav-close aria-label="Tutup menu" class="inline-grid size-9 cursor-pointer place-items-center rounded-xl text-mute transition hover:bg-tint hover:text-ink md:hidden">
        <x-icon name="x" class="size-5" />
      </button>
    </div>

    <nav class="relative flex flex-1 flex-col gap-1 overflow-y-auto px-3 pb-6" aria-label="Menu admin">
      <div class="px-3 pt-2 pb-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-mute">Menu</div>
      @foreach ($menu as [$route, $pattern, $label, $icon, $hint])
        @php($active = request()->routeIs($pattern))
        <a href="{{ route($route) }}" @if ($active) aria-current="page" @endif
           class="group relative flex items-center gap-3 rounded-2xl px-3 py-2.5 transition {{ $active ? 'bg-tint text-ink ring-1 ring-brand/15' : 'text-mute hover:bg-paper hover:text-ink' }}">
          @if ($active)<span class="absolute inset-y-2.5 -left-3 w-1 rounded-r-full bg-[var(--gold)]"></span>@endif
          <span class="grid size-9 shrink-0 place-items-center rounded-xl transition {{ $active ? 'bg-brand text-paper shadow-sm' : 'bg-paper text-mute group-hover:text-brand' }}">
            <x-icon :name="$icon" class="size-[18px]" />
          </span>
          <span class="min-w-0 leading-tight">
            <span class="block truncate text-sm font-semibold {{ $active ? 'text-brand' : '' }}">{{ $label }}</span>
            <span class="block truncate text-[11px] text-mute">{{ $hint }}</span>
          </span>
        </a>
      @endforeach
    </nav>
  </aside>

  {{-- Konten --}}
  <section class="flex min-w-0 flex-col bg-aurora">
    <header class="relative flex flex-col gap-4 px-4 pt-6 pb-2 sm:flex-row sm:items-end sm:justify-between sm:px-8 sm:pt-8">
      <div class="min-w-0 flex-1" data-reveal>
        <nav class="flex items-center gap-1.5 text-xs font-medium text-mute" aria-label="Breadcrumb">
          <x-icon name="shield" class="size-3.5 text-brand" /><span>Admin</span><x-icon name="chevron" class="size-3" /><span class="text-ink">@yield('title')</span>
        </nav>
        <h1 class="mt-2 font-serif text-3xl font-bold tracking-tight">@yield('title')</h1>
        <p class="mt-1 max-w-2xl text-sm leading-6 text-mute">@yield('subheading')</p>
      </div>

      <div class="hidden shrink-0 items-center gap-2 md:flex" data-reveal>
        @yield('actions')
        <button type="button" data-theme-toggle title="Ganti tema" aria-label="Ganti tema" class="inline-flex h-10 cursor-pointer items-center gap-2 rounded-xl border border-line bg-card px-3 text-sm font-medium text-mute shadow-card transition hover:border-brand hover:text-ink">
          <span data-theme-icon="light"><x-icon name="sun" class="size-4" /></span>
          <span data-theme-icon="dark" class="hidden"><x-icon name="moon" class="size-4" /></span>
          <span data-theme-icon="system" class="hidden"><x-icon name="monitor" class="size-4" /></span>
          <span data-theme-label>Tema</span>
        </button>
        {{-- Akun admin + Keluar di kanan atas. --}}
        <div class="flex h-10 items-center gap-2.5 rounded-xl border border-line bg-card pr-1 pl-1 shadow-card">
          <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-gradient-to-br from-brand to-brand-strong text-xs font-bold text-paper">{{ $initials }}</span>
          <div class="hidden max-w-[12rem] leading-tight lg:block">
            <div class="truncate text-sm font-semibold">{{ $admin->name }}</div>
            <div class="truncate text-[11px] text-mute">{{ $admin->email }}</div>
          </div>
          <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-lg px-2.5 text-sm font-semibold text-mute transition hover:bg-danger-bg hover:text-danger-ink">
              <x-icon name="logout" class="size-4" />Keluar
            </button>
          </form>
        </div>
      </div>
    </header>

    <div class="flex-1 space-y-6 px-4 py-6 sm:px-8">
      @yield('content')
    </div>

    <footer class="px-4 pb-6 text-xs text-mute sm:px-8">LLM Surabaya · Dinas Komunikasi dan Informatika Kota Surabaya</footer>
  </section>
</div>
@include('partials.dismiss-script')
@stack('scripts')
</body>
</html>

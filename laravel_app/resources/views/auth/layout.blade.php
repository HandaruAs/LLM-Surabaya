<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
@include('partials.head', ['title' => trim($__env->yieldContent('title'))])
</head>
<body class="min-h-full bg-paper font-sans text-ink">
<div class="grid min-h-dvh lg:grid-cols-2">

  {{-- Panel biru (disembunyikan di layar kecil) --}}
  <div class="relative hidden flex-col justify-between overflow-hidden bg-brand p-10 text-paper lg:flex">
    <svg class="pointer-events-none absolute -right-10 -top-6 h-80 w-80 text-paper opacity-10" viewBox="0 0 230 230" aria-hidden="true"><polygon points="115 6 224 56 6 56" fill="currentColor"/><rect x="34" y="70" width="18" height="110" rx="9" fill="currentColor"/><rect x="82" y="70" width="18" height="110" rx="9" fill="currentColor"/><rect x="130" y="70" width="18" height="110" rx="9" fill="currentColor"/><rect x="178" y="70" width="18" height="110" rx="9" fill="currentColor"/><rect x="22" y="194" width="186" height="16" rx="8" fill="currentColor"/></svg>

    <a href="{{ route('chat') }}" class="relative flex items-center gap-3 self-start">
      <span class="grid size-10 place-items-center rounded-xl bg-paper text-brand"><x-icon name="landmark" class="size-5" /></span>
      <span class="font-serif text-lg font-semibold">LLM Surabaya</span>
    </a>

    <div class="relative">
      <div class="mb-4 h-[3px] w-14 rounded-full bg-gold"></div>
      <h2 class="max-w-sm font-serif text-3xl font-semibold leading-tight">Tanya jawab keuangan daerah, lengkap dengan rujukan.</h2>
      <p class="mt-3 max-w-sm text-sm leading-6 opacity-90">Tanyakan seputar pajak, anggaran, dan retribusi Kota Surabaya. Jawaban disusun dari dokumen resmi.</p>
      <ul class="mt-6 space-y-2.5 text-sm">
        <li class="flex items-center gap-2.5"><x-icon name="check" class="size-[18px]" />Jawaban selalu dengan rujukan dokumen</li>
        <li class="flex items-center gap-2.5"><x-icon name="check" class="size-[18px]" />Riwayat percakapan tersimpan di akunmu</li>
        <li class="flex items-center gap-2.5"><x-icon name="check" class="size-[18px]" />Tanpa akun pun tetap bisa bertanya</li>
      </ul>
    </div>

    <p class="relative text-xs opacity-75">Dinas Komunikasi dan Informatika Kota Surabaya</p>
  </div>

  {{-- Form --}}
  <div class="flex items-center justify-center px-4 py-10 sm:p-10">
    <div class="w-full max-w-sm">
      <a href="{{ route('chat') }}" class="mb-8 flex items-center gap-3 lg:hidden">
        <span class="grid size-10 place-items-center rounded-xl bg-brand text-paper shadow-sm"><x-icon name="landmark" class="size-5" /></span>
        <span class="font-serif text-lg font-semibold">LLM Surabaya</span>
      </a>

      <h1 class="font-serif text-3xl font-semibold">@yield('heading')</h1>
      <p class="mt-2 text-sm text-mute">@yield('subheading')</p>

      @yield('alert')

      @yield('form')

      <p class="mt-6 text-center text-sm text-mute">@yield('footer')</p>
      <p class="mt-3 text-center text-sm">
        <a href="{{ route('chat') }}" class="text-mute hover:text-ink hover:underline">Lanjut tanpa masuk</a>
      </p>
    </div>
  </div>
</div>
@stack('scripts')
</body>
</html>

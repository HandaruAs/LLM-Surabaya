<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
@include('partials.head', ['title' => trim($__env->yieldContent('title'))])
@vite('resources/js/auth.js')
{{-- Sembunyikan isi sebentar sampai animasi masuk dimulai (resources/js/auth.js).
     Pengaman: kalau script gagal dimuat, isi tetap muncul setelah 1,5 detik. --}}
<style nonce="{{ Vite::cspNonce() }}">.auth-anim [data-auth-card], .auth-anim [data-anim-panel] { opacity: 0; }</style>
<script nonce="{{ Vite::cspNonce() }}">
  if (!matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.documentElement.classList.add('auth-anim');
    setTimeout(() => document.documentElement.classList.remove('auth-anim'), 1500);
  }
</script>
</head>
<body class="min-h-full bg-paper font-sans text-ink">
<div class="grid min-h-dvh lg:grid-cols-2">

  {{-- Panel foto (disembunyikan di layar kecil). Foto per halaman: @section('panel_image') di login/register. --}}
  <div class="relative hidden flex-col justify-between overflow-hidden bg-ink p-10 text-white lg:flex">
    <img data-anim-photo src="{{ asset(trim($__env->yieldContent('panel_image', 'images/auth-login.webp'))) }}" alt=""
         class="pointer-events-none absolute inset-0 size-full object-cover" style="object-position: {{ trim($__env->yieldContent('panel_position', 'center')) }}">
    {{-- Lapisan gelap supaya teks putih tetap terbaca di atas foto. --}}
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/85 via-black/40 to-black/30"></div>

    <a href="{{ route('chat') }}" data-anim-panel class="relative flex items-center gap-3 self-start">
      <img src="{{ asset('images/lambang-surabaya.webp') }}" alt="Lambang Kota Surabaya" width="31" height="40" class="h-10 w-auto shrink-0 drop-shadow">
      <span class="font-serif text-lg font-semibold drop-shadow">LLM Surabaya</span>
    </a>

    <div data-anim-panel class="relative [text-shadow:0_1px_12px_rgb(0_0_0/0.35)]">
      <div class="mb-4 h-[3px] w-14 rounded-full bg-gold"></div>
      <h2 class="max-w-sm font-serif text-3xl font-semibold leading-tight">Tanya jawab keuangan daerah, lengkap dengan rujukan.</h2>
      <p class="mt-3 max-w-sm text-sm leading-6 text-white/85">Tanyakan seputar pajak, anggaran, dan retribusi Kota Surabaya. Jawaban disusun dari dokumen resmi.</p>
      <ul class="mt-6 space-y-2.5 text-sm">
        <li class="flex items-center gap-2.5"><x-icon name="check" class="size-[18px]" />Jawaban selalu dengan rujukan dokumen</li>
        <li class="flex items-center gap-2.5"><x-icon name="check" class="size-[18px]" />Riwayat percakapan tersimpan di akunmu</li>
        <li class="flex items-center gap-2.5"><x-icon name="check" class="size-[18px]" />Tanpa akun pun tetap bisa bertanya</li>
      </ul>
    </div>

    <p data-anim-panel class="relative text-xs text-white/75">Dinas Komunikasi dan Informatika Kota Surabaya</p>
  </div>

  {{-- Form --}}
  <div class="flex items-center justify-center px-4 py-10 sm:p-10">
    <div data-auth-card class="w-full max-w-sm">
      <a href="{{ route('chat') }}" data-anim class="mb-8 flex items-center gap-3 lg:hidden">
        <img src="{{ asset('images/lambang-surabaya.webp') }}" alt="Lambang Kota Surabaya" width="31" height="40" class="h-10 w-auto shrink-0">
        <span class="font-serif text-lg font-semibold">LLM Surabaya</span>
      </a>

      <h1 data-anim class="font-serif text-3xl font-semibold">@yield('heading')</h1>
      <p data-anim class="mt-2 text-sm text-mute">@yield('subheading')</p>

      <div data-anim>@yield('alert')</div>

      <div data-anim>@yield('form')</div>

      <p data-anim class="mt-6 text-center text-sm text-mute">@yield('footer')</p>
      <p data-anim class="mt-3 text-center text-sm">
        <a href="{{ route('chat') }}" class="text-mute hover:text-ink hover:underline">Lanjut tanpa masuk</a>
      </p>
    </div>
  </div>
</div>
@stack('scripts')
</body>
</html>

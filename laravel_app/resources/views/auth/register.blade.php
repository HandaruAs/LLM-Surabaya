@extends('auth.layout')

@section('title', 'Daftar')
@section('heading', 'Buat akun')
@section('subheading', 'Dengan akun, riwayat percakapanmu tersimpan dan bisa dibuka dari perangkat lain.')

@section('form')
<form method="POST" action="{{ route('register.store') }}" class="mt-6" novalidate>
  @csrf
  <div class="space-y-4">
    <x-field label="Nama lengkap" name="name" icon="user" placeholder="Nama Anda"
             required autofocus autocomplete="name" maxlength="100" />
    <x-field label="Email" name="email" type="email" icon="mail" placeholder="nama@email.com"
             required autocomplete="email" />
    <x-field label="Kata sandi" name="password" type="password" icon="lock" placeholder="Minimal 8 karakter"
             required minlength="8" autocomplete="new-password" data-strength-input>
      {{-- Indikator kekuatan kata sandi (diisi script di bawah). --}}
      <span class="mt-2.5 block hidden" data-strength>
        <span class="grid grid-cols-4 gap-1.5">
          <span class="h-1 rounded-full bg-line" data-bar></span><span class="h-1 rounded-full bg-line" data-bar></span><span class="h-1 rounded-full bg-line" data-bar></span><span class="h-1 rounded-full bg-line" data-bar></span>
        </span>
        <span class="mt-1.5 block text-xs" data-strength-label></span>
      </span>
    </x-field>
    <x-field label="Ulangi kata sandi" name="password_confirmation" type="password" icon="lock" placeholder="Ketik ulang kata sandi"
             required minlength="8" autocomplete="new-password" />
  </div>

  <button type="submit" class="mt-6 inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-brand px-4 py-3 text-sm font-semibold text-paper shadow-sm transition hover:bg-brand-strong active:translate-y-px">
    Buat akun
  </button>
</form>
@endsection

@section('footer')
  Sudah punya akun? <a href="{{ route('login') }}" class="font-semibold text-brand hover:underline">Masuk</a>
@endsection

@push('scripts')
<script nonce="{{ Vite::cspNonce() }}">
  // Indikator kekuatan kata sandi: hanya petunjuk, aturan sebenarnya dicek server (min. 8 karakter).
  (() => {
    const input = document.querySelector('[data-strength-input]');
    const box = document.querySelector('[data-strength]');
    if (!input || !box) return;
    const bars = box.querySelectorAll('[data-bar]');
    const label = box.querySelector('[data-strength-label]');
    const levels = [
      ['Terlalu pendek', 'text-danger-ink', 'bg-danger'],
      ['Lemah', 'text-danger-ink', 'bg-danger'],
      ['Cukup', 'text-warning-ink', 'bg-gold'],
      ['Kuat', 'text-success-ink', 'bg-success-ink'],
      ['Sangat kuat', 'text-success-ink', 'bg-success-ink'],
    ];
    input.addEventListener('input', () => {
      const v = input.value;
      box.classList.toggle('hidden', v.length === 0);
      let score = 0;
      if (v.length >= 8) {
        score = 1;
        if (v.length >= 12) score++;
        if (/[a-z]/.test(v) && /[A-Z]/.test(v)) score++;
        if (/\d/.test(v) && /[^A-Za-z0-9]/.test(v)) score++;
      }
      const [text, textClass, barClass] = levels[score];
      bars.forEach((bar, i) => {
        bar.className = 'h-1 rounded-full ' + (i < Math.max(score, v.length ? 1 : 0) ? barClass : 'bg-line');
      });
      label.className = 'mt-1.5 block text-xs ' + textClass;
      label.textContent = 'Kata sandi ' + text.toLowerCase();
    });
  })();
</script>
@endpush

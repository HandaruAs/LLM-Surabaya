@extends('auth.layout')

@section('title', 'Masuk')
@section('heading', 'Masuk')
@section('subheading', 'Masuk agar riwayat percakapanmu tersimpan dan bisa dibuka lagi.')

@section('form')
<form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-4">
  @csrf
  <label class="flex flex-col gap-1.5 text-sm font-medium">
    Email
    <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
           class="rounded-lg border border-line bg-paper px-3 py-2.5 font-normal outline-none focus:border-brand">
  </label>
  <label class="flex flex-col gap-1.5 text-sm font-medium">
    Kata sandi
    <input type="password" name="password" required autocomplete="current-password"
           class="rounded-lg border border-line bg-paper px-3 py-2.5 font-normal outline-none focus:border-brand">
  </label>
  <label class="flex items-center gap-2 text-sm text-mute">
    <input type="checkbox" name="remember" value="1" class="size-4 accent-[var(--brand)]">
    Ingat saya
  </label>
  <button type="submit" class="mt-1 cursor-pointer rounded-lg bg-brand px-4 py-2.5 font-medium text-paper hover:opacity-90">
    Masuk
  </button>
</form>
@endsection

@section('footer')
  Belum punya akun? <a href="{{ route('register') }}" class="font-medium text-brand hover:underline">Daftar</a>
  <span class="mx-1.5">·</span>
  <a href="{{ route('chat') }}" class="hover:text-ink hover:underline">Lanjut tanpa masuk</a>
@endsection

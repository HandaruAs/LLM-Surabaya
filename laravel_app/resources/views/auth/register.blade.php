@extends('auth.layout')

@section('title', 'Daftar')
@section('heading', 'Buat akun')
@section('subheading', 'Dengan akun, riwayat percakapanmu tersimpan dan bisa dibuka dari perangkat lain.')

@section('form')
<form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-4">
  @csrf
  <label class="flex flex-col gap-1.5 text-sm font-medium">
    Nama
    <input type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" maxlength="100"
           class="rounded-lg border border-line bg-paper px-3 py-2.5 font-normal outline-none focus:border-brand">
  </label>
  <label class="flex flex-col gap-1.5 text-sm font-medium">
    Email
    <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email"
           class="rounded-lg border border-line bg-paper px-3 py-2.5 font-normal outline-none focus:border-brand">
  </label>
  <label class="flex flex-col gap-1.5 text-sm font-medium">
    Kata sandi
    <input type="password" name="password" required minlength="8" autocomplete="new-password"
           class="rounded-lg border border-line bg-paper px-3 py-2.5 font-normal outline-none focus:border-brand">
    <span class="text-xs font-normal text-mute">Minimal 8 karakter.</span>
  </label>
  <label class="flex flex-col gap-1.5 text-sm font-medium">
    Ulangi kata sandi
    <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"
           class="rounded-lg border border-line bg-paper px-3 py-2.5 font-normal outline-none focus:border-brand">
  </label>
  <button type="submit" class="mt-1 cursor-pointer rounded-lg bg-brand px-4 py-2.5 font-medium text-paper hover:opacity-90">
    Daftar
  </button>
</form>
@endsection

@section('footer')
  Sudah punya akun? <a href="{{ route('login') }}" class="font-medium text-brand hover:underline">Masuk</a>
  <span class="mx-1.5">·</span>
  <a href="{{ route('chat') }}" class="hover:text-ink hover:underline">Lanjut tanpa masuk</a>
@endsection

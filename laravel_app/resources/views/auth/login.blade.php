@extends('auth.layout')

@section('title', 'Masuk')
@section('heading', 'Masuk')
@section('subheading', 'Masuk agar riwayat percakapanmu tersimpan dan bisa dibuka lagi.')

@php($loginFailed = $errors->first('email') === 'Email atau kata sandi salah.')

@section('alert')
  @if ($loginFailed)
    <x-alert type="danger" title="Login gagal" class="mt-6">Email atau kata sandi tidak sesuai.</x-alert>
  @endif
@endsection

@section('form')
{{-- autocomplete="off": jangan isi otomatis email/kata sandi yang tersimpan di browser. --}}
<form method="POST" action="{{ route('login.store') }}" class="mt-6" novalidate autocomplete="off">
  @csrf
  <div class="space-y-4">
    <x-field label="Email" name="email" type="email" icon="mail" placeholder="nama@email.com"
             required autofocus autocomplete="off" :show-error="! $loginFailed" />
    <x-field label="Kata sandi" name="password" type="password" icon="lock" placeholder="••••••••"
             required autocomplete="new-password" />
  </div>

  <label class="mt-4 flex items-center gap-2 text-sm text-mute">
    <input type="checkbox" name="remember" value="1" class="size-4 rounded border-line accent-[var(--brand)]">
    Ingat saya
  </label>

  <button type="submit" class="mt-6 inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-brand px-4 py-3 text-sm font-semibold text-paper shadow-sm transition hover:bg-brand-strong active:translate-y-px">
    Masuk<x-icon name="arrow" class="size-4" />
  </button>
</form>
@endsection

@section('footer')
  Belum punya akun? <a href="{{ route('register') }}" class="font-semibold text-brand hover:underline">Daftar</a>
@endsection

@push('scripts')
  @include('partials.dismiss-script')
@endpush

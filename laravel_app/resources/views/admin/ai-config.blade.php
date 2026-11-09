@extends('admin.layout')

@section('title', 'Konfigurasi AI')
@section('subheading', 'Atur cara chatbot menjawab. Perubahan berlaku mulai pertanyaan berikutnya. Cek hasilnya di menu Chatbot AI.')

@section('actions')
  <a href="{{ route('admin.ai-chat') }}" class="inline-flex h-10 items-center gap-2 rounded-xl border border-line bg-card px-3.5 text-sm font-semibold shadow-card transition hover:border-brand hover:text-brand">
    <x-icon name="bot" class="size-4" />Uji di Chatbot AI
  </a>
@endsection

@section('content')
  {{-- Halaman ini dirender React (resources/js/admin/AiConfig.jsx). --}}
  <div data-react="ai-config" data-props="{{ json_encode([
      'config' => $config,
      'saveUrl' => route('admin.ai-config'),
      'csrf' => csrf_token(),
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}">
    <div class="max-w-4xl space-y-6">
      <div class="h-40 animate-pulse rounded-3xl bg-card"></div>
      <div class="h-72 animate-pulse rounded-3xl bg-card"></div>
    </div>
  </div>
  <noscript><p class="text-sm text-danger-ink">Halaman ini membutuhkan JavaScript.</p></noscript>
@endsection

@extends('admin.layout')

@section('title', 'Ringkasan')
@section('subheading', 'Gambaran singkat aktivitas chatbot, dokumen rujukan, dan status layanan AI.')

@section('content')
  {{-- Isi halaman dirender React (resources/js/admin/Overview.jsx) dari data di bawah. --}}
  <div data-react="overview" data-props="{{ json_encode(['stats' => $stats], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      @for ($i = 0; $i < 4; $i++)
        <div class="h-40 animate-pulse rounded-3xl bg-card"></div>
      @endfor
    </div>
  </div>
@endsection

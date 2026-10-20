@props(['type' => 'success', 'title' => null])
@php
    $tone = match ($type) {
        'danger' => 'bg-danger-bg text-danger-ink',
        'warning' => 'bg-warning-bg text-warning-ink',
        default => 'bg-success-bg text-success-ink',
    };
    $icon = $type === 'success' ? 'check' : 'alert';
@endphp
{{-- Pesan yang bisa ditutup (tombol x ditangani script data-dismiss di halaman). --}}
<div role="{{ $type === 'success' ? 'status' : 'alert' }}" data-alert {{ $attributes->class(['flex items-start gap-3 rounded-xl px-4 py-3', $tone]) }}>
  <x-icon :name="$icon" class="mt-0.5 size-5" />
  <div class="min-w-0 flex-1 text-sm">
    @if ($title)<p class="font-semibold">{{ $title }}</p>@endif
    <div @class(['mt-0.5 opacity-90' => $title])>{{ $slot }}</div>
  </div>
  <button type="button" data-dismiss aria-label="Tutup" class="-m-1 cursor-pointer rounded-md p-1 opacity-70 transition hover:opacity-100"><x-icon name="x" class="size-4" /></button>
</div>

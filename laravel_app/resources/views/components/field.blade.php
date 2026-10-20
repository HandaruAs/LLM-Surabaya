@props([
    'label',
    'name',
    'type' => 'text',
    'icon' => null,
    'hint' => null,
    'showError' => true,
])
@php($hasError = $showError && $errors->has($name))
<label class="block">
  <span class="mb-1.5 block text-sm font-semibold">{{ $label }}</span>
  <span class="relative block">
    @if ($icon)
      <span class="pointer-events-none absolute inset-y-0 left-3.5 grid place-items-center text-mute"><x-icon :name="$icon" class="size-4" /></span>
    @endif
    <input type="{{ $type }}" name="{{ $name }}"
           @if ($type !== 'password') value="{{ old($name) }}" @endif
           @if ($hasError) aria-invalid="true" @endif
           {{ $attributes->class(['field', 'pl-10' => $icon]) }}>
  </span>
  @if ($hasError)
    <span class="mt-1.5 flex items-center gap-1.5 text-xs font-medium text-danger-ink"><x-icon name="alert" class="size-3.5" />{{ $errors->first($name) }}</span>
  @elseif ($hint)
    <span class="mt-1.5 block text-xs text-mute">{{ $hint }}</span>
  @endif
  {{ $slot }}
</label>

@extends('admin.layout')

@section('title', 'Manajemen admin')
@section('subheading', 'Tambah, ubah, dan hapus akun admin yang dapat mengakses area pengelolaan.')

@php
    $initialsOf = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)
        ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') ?: 'AD';
    $palette = ['from-emerald-500 to-emerald-700', 'from-amber-400 to-amber-600', 'from-sky-500 to-sky-700', 'from-rose-400 to-rose-600', 'from-violet-500 to-violet-700'];
@endphp

@section('content')
@if (session('status'))
  <x-alert type="success">{{ session('status') }}</x-alert>
@endif
@if ($errors->has('account'))
  <x-alert type="danger">{{ $errors->first('account') }}</x-alert>
@endif

<div class="grid gap-6 xl:grid-cols-[minmax(0,22rem)_minmax(0,1fr)] xl:items-start">
  {{-- Tambah akun --}}
  <section data-reveal class="rounded-3xl border border-line bg-card p-5 shadow-card sm:p-6 xl:sticky xl:top-6">
    <div class="flex items-start gap-3">
      <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-tint text-brand"><x-icon name="plus" class="size-5" /></span>
      <div>
        <h2 class="font-serif text-lg font-bold">Tambah akun admin</h2>
        <p class="text-xs text-mute">Kata sandi minimal 12 karakter.</p>
      </div>
    </div>
    <form method="POST" action="{{ route('admin.accounts.store') }}" class="mt-5 space-y-4">
      @csrf
      <label class="block text-sm font-semibold">Nama
        <input name="name" value="{{ old('name') }}" required maxlength="255" class="field mt-1.5 font-normal" autocomplete="name" placeholder="Nama lengkap">
        @error('name') <span class="mt-1 block text-xs font-medium text-danger-ink">{{ $message }}</span> @enderror
      </label>
      <label class="block text-sm font-semibold">Email
        <input name="email" type="email" value="{{ old('email') }}" required maxlength="255" class="field mt-1.5 font-normal" autocomplete="email" placeholder="admin@surabaya.go.id">
        @error('email') <span class="mt-1 block text-xs font-medium text-danger-ink">{{ $message }}</span> @enderror
      </label>
      <label class="block text-sm font-semibold">Kata sandi
        <input name="password" type="password" required minlength="12" class="field mt-1.5 font-normal" autocomplete="new-password" placeholder="Minimal 12 karakter">
        @error('password') <span class="mt-1 block text-xs font-medium text-danger-ink">{{ $message }}</span> @enderror
      </label>
      <label class="block text-sm font-semibold">Konfirmasi kata sandi
        <input name="password_confirmation" type="password" required minlength="12" class="field mt-1.5 font-normal" autocomplete="new-password" placeholder="Ketik ulang kata sandi">
      </label>
      <button type="submit" class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-gradient-to-br from-brand to-brand-strong px-4 py-3 text-sm font-semibold text-paper shadow-glow transition active:translate-y-px">
        <x-icon name="plus" class="size-4" />Tambah admin
      </button>
    </form>
  </section>

  {{-- Daftar akun --}}
  <section data-reveal class="overflow-hidden rounded-3xl border border-line bg-card shadow-card">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-5 py-4 sm:px-6">
      <div>
        <h2 class="font-serif text-lg font-bold">Daftar akun admin</h2>
        <p class="text-xs text-mute">Admin yang sedang dipakai tidak bisa dihapus.</p>
      </div>
      <span class="rounded-full bg-tint px-3 py-1 text-xs font-semibold text-brand">{{ $admins->total() }} akun</span>
    </div>
    @if ($admins->isEmpty())
      <p class="px-5 py-12 text-center text-sm text-mute">Belum ada akun admin.</p>
    @else
      <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] text-left text-sm">
          <thead class="bg-paper/70 text-[11px] uppercase tracking-wider text-mute">
            <tr>
              <th scope="col" class="px-5 py-3 font-semibold sm:pl-6">Admin</th>
              <th scope="col" class="px-5 py-3 font-semibold">Status</th>
              <th scope="col" class="px-5 py-3 text-right font-semibold sm:pr-6">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line">
            @foreach ($admins as $account)
              @php($isMe = auth('web')->id() === $account->id)
              <tr data-reveal-row class="align-middle transition hover:bg-paper/70">
                <td class="px-5 py-4 sm:pl-6">
                  <div class="flex items-center gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-gradient-to-br {{ $palette[$account->id % count($palette)] }} text-sm font-bold text-white">{{ $initialsOf($account->name) }}</span>
                    <div class="min-w-0">
                      <div class="truncate font-semibold">{{ $account->name }}</div>
                      <div class="break-all text-xs text-mute">{{ $account->email }}</div>
                    </div>
                  </div>
                </td>
                <td class="px-5 py-4">
                  @if ($isMe)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-tint px-2.5 py-1 text-xs font-semibold text-brand"><span class="size-1.5 rounded-full bg-brand"></span>Akun kamu</span>
                  @else
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-paper px-2.5 py-1 text-xs font-medium text-mute"><x-icon name="shield" class="size-3.5" />Admin</span>
                  @endif
                </td>
                <td class="px-5 py-4 sm:pr-6">
                  <div class="flex justify-end gap-2">
                    <button type="button" data-edit="edit-admin-{{ $account->id }}" aria-expanded="false" class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-line px-3 py-2 text-xs font-semibold transition hover:border-brand hover:text-brand">
                      <x-icon name="edit" class="size-3.5" />Edit
                    </button>
                    @if (! $isMe)
                      <button type="button"
                              data-delete-url="{{ route('admin.accounts.destroy', $account) }}"
                              data-delete-name="{{ $account->email }}"
                              class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-semibold text-mute transition hover:bg-danger-bg hover:text-danger-ink">
                        <x-icon name="trash" class="size-3.5" />Hapus
                      </button>
                    @endif
                  </div>
                </td>
              </tr>
              <tr id="edit-admin-{{ $account->id }}" class="hidden bg-paper/70">
                <td colspan="3" class="px-5 py-5 sm:px-6">
                  <form method="POST" action="{{ route('admin.accounts.update', $account) }}" class="grid gap-3 md:grid-cols-2">
                    @csrf
                    @method('PUT')
                    <label class="text-xs font-semibold text-mute">Nama
                      <input name="name" value="{{ $account->name }}" required maxlength="255" class="field mt-1 text-sm">
                      @error('name') <span class="mt-1 block text-xs text-danger-ink">{{ $message }}</span> @enderror
                    </label>
                    <label class="text-xs font-semibold text-mute">Email
                      <input name="email" type="email" value="{{ $account->email }}" required maxlength="255" class="field mt-1 text-sm">
                      @error('email') <span class="mt-1 block text-xs text-danger-ink">{{ $message }}</span> @enderror
                    </label>
                    <label class="text-xs font-semibold text-mute">Kata sandi baru <span class="font-normal">(kosongkan jika tidak diubah)</span>
                      <input name="password" type="password" minlength="12" class="field mt-1 text-sm" autocomplete="new-password">
                      @error('password') <span class="mt-1 block text-xs text-danger-ink">{{ $message }}</span> @enderror
                    </label>
                    <label class="text-xs font-semibold text-mute">Konfirmasi kata sandi baru
                      <input name="password_confirmation" type="password" minlength="12" class="field mt-1 text-sm" autocomplete="new-password">
                    </label>
                    <div class="flex flex-wrap gap-2 md:col-span-2">
                      <button type="submit" class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-paper transition hover:bg-brand-strong"><x-icon name="check" class="size-4" />Simpan perubahan</button>
                      <button type="button" data-edit="edit-admin-{{ $account->id }}" class="cursor-pointer rounded-xl border border-line px-4 py-2 text-sm font-semibold transition hover:bg-tint">Batal</button>
                    </div>
                  </form>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @if ($admins->hasPages())
        <div class="border-t border-line px-5 py-4">{{ $admins->links() }}</div>
      @endif
    @endif
  </section>
</div>

{{-- Dialog konfirmasi hapus akun --}}
<dialog id="confirmDeleteAccount" aria-labelledby="deleteAccountTitle"
        class="m-auto w-[calc(100%-2rem)] max-w-md rounded-3xl border border-line bg-card p-0 text-ink shadow-lift">
  <form method="POST" id="deleteAccountForm" class="p-6">
    @csrf
    @method('DELETE')
    <span class="grid size-12 place-items-center rounded-2xl bg-danger-bg text-danger-ink"><x-icon name="trash" class="size-5" /></span>
    <h2 id="deleteAccountTitle" class="mt-4 font-serif text-xl font-bold">Hapus akun admin?</h2>
    <p class="mt-2 text-sm leading-6 text-mute">Akun <span id="deleteAccountName" class="break-all font-semibold text-ink"></span> beserta seluruh riwayat chat miliknya akan dihapus dan tidak dapat dipulihkan.</p>
    <div class="mt-6 grid grid-cols-2 gap-2">
      <button type="button" id="cancelDeleteAccount" class="cursor-pointer rounded-xl border border-line px-4 py-2.5 text-sm font-semibold transition hover:bg-tint">Batal</button>
      <button type="submit" class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-xl bg-danger-ink px-4 py-2.5 text-sm font-semibold text-card transition hover:opacity-90"><x-icon name="trash" class="size-4" />Hapus akun</button>
    </div>
  </form>
</dialog>
@endsection

@push('scripts')
<script nonce="{{ Vite::cspNonce() }}">
  (() => {
    // Buka/tutup form edit di bawah baris akun.
    document.querySelectorAll('[data-edit]').forEach(button => {
      button.addEventListener('click', () => {
        const row = document.getElementById(button.dataset.edit);
        const open = row.classList.toggle('hidden') === false;
        document.querySelectorAll('[data-edit="' + button.dataset.edit + '"][aria-expanded]').forEach(b => b.setAttribute('aria-expanded', String(open)));
        if (open) row.querySelector('input')?.focus();
      });
    });

    // Konfirmasi hapus memakai dialog (inline onsubmit diblokir oleh Content-Security-Policy).
    const dialog = document.getElementById('confirmDeleteAccount');
    const form = document.getElementById('deleteAccountForm');
    document.querySelectorAll('[data-delete-url]').forEach(button => {
      button.addEventListener('click', () => {
        form.action = button.dataset.deleteUrl;
        document.getElementById('deleteAccountName').textContent = button.dataset.deleteName;
        dialog.showModal();
      });
    });
    document.getElementById('cancelDeleteAccount').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', e => { if (e.target === dialog) dialog.close(); });

    // Form edit yang gagal validasi dibuka lagi supaya pesan errornya terlihat.
    @if ($errors->hasAny(['name', 'email', 'password']) && old('_method') === 'PUT')
      document.querySelector('tr[id^="edit-admin-"] .text-danger-ink')?.closest('tr')?.classList.remove('hidden');
    @endif
  })();
</script>
@endpush
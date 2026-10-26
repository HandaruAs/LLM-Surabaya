@extends('admin.layout')

@section('title', 'Manajemen admin')
@section('subheading', 'Tambah, ubah, dan hapus akun admin yang dapat mengakses area pengelolaan.')

@section('content')
@if (session('status'))
  <div role="status" class="rounded-xl border border-success-ink/20 bg-success-bg px-4 py-3 text-sm text-success-ink">{{ session('status') }}</div>
@endif
@if ($errors->has('account'))
  <div role="alert" class="rounded-xl border border-danger-ink/20 bg-danger-bg px-4 py-3 text-sm text-danger-ink">{{ $errors->first('account') }}</div>
@endif

<section class="rounded-2xl border border-line bg-card p-5 shadow-card">
  <div class="flex flex-wrap items-baseline justify-between gap-2">
    <h2 class="font-serif text-lg font-semibold">Tambah akun admin</h2>
    <p class="text-xs text-mute">Kata sandi minimal 12 karakter.</p>
  </div>
  <form method="POST" action="{{ route('admin.accounts.store') }}" class="mt-4 grid gap-4 md:grid-cols-2">
    @csrf
    <label class="block text-sm font-medium">Nama
      <input name="name" value="{{ old('name') }}" required maxlength="255" class="field mt-1" autocomplete="name">
      @error('name') <span class="mt-1 block text-xs text-danger-ink">{{ $message }}</span> @enderror
    </label>
    <label class="block text-sm font-medium">Email
      <input name="email" type="email" value="{{ old('email') }}" required maxlength="255" class="field mt-1" autocomplete="email">
      @error('email') <span class="mt-1 block text-xs text-danger-ink">{{ $message }}</span> @enderror
    </label>
    <label class="block text-sm font-medium">Kata sandi
      <input name="password" type="password" required minlength="12" class="field mt-1" autocomplete="new-password">
      @error('password') <span class="mt-1 block text-xs text-danger-ink">{{ $message }}</span> @enderror
    </label>
    <label class="block text-sm font-medium">Konfirmasi kata sandi
      <input name="password_confirmation" type="password" required minlength="12" class="field mt-1" autocomplete="new-password">
    </label>
    <div class="md:col-span-2">
      <button type="submit" class="inline-flex cursor-pointer items-center justify-center rounded-xl bg-brand px-4 py-2.5 text-sm font-semibold text-paper shadow-sm transition hover:bg-brand-strong">Tambah admin</button>
    </div>
  </form>
</section>

<section class="overflow-hidden rounded-2xl border border-line bg-card shadow-card">
  <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-line px-5 py-4">
    <h2 class="font-serif text-lg font-semibold">Daftar akun admin</h2>
    <span class="text-xs text-mute">{{ $admins->total() }} akun</span>
  </div>
  @if ($admins->isEmpty())
    <p class="px-5 py-12 text-center text-sm text-mute">Belum ada akun admin.</p>
  @else
    <div class="overflow-x-auto">
      <table class="w-full min-w-[760px] text-left text-sm">
        <thead class="bg-tint text-xs uppercase tracking-wider text-mute">
          <tr>
            <th scope="col" class="px-5 py-3 font-semibold">Nama</th>
            <th scope="col" class="px-5 py-3 font-semibold">Email</th>
            <th scope="col" class="px-5 py-3 font-semibold">Status</th>
            <th scope="col" class="px-5 py-3 text-right font-semibold">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-line">
          @foreach ($admins as $account)
            <tr class="align-middle">
              <td class="px-5 py-4 font-medium">{{ $account->name }}</td>
              <td class="break-all px-5 py-4 text-mute">{{ $account->email }}</td>
              <td class="px-5 py-4">
                @if (auth('web')->id() === $account->id)
                  <span class="inline-flex rounded-full bg-tint px-2.5 py-1 text-xs font-medium text-brand">Akun kamu</span>
                @else
                  <span class="text-mute">Admin</span>
                @endif
              </td>
              <td class="px-5 py-4">
                <div class="flex justify-end gap-2">
                  <button type="button" data-edit="edit-admin-{{ $account->id }}" class="cursor-pointer rounded-lg border border-brand px-3 py-1.5 text-xs font-semibold text-brand transition hover:bg-tint">Edit</button>
                  @if (auth('web')->id() !== $account->id)
                    <form method="POST" action="{{ route('admin.accounts.destroy', $account) }}" onsubmit="return confirm('Hapus akun {{ addslashes($account->email) }}? Seluruh riwayat chat milik akun ini juga akan terhapus dan tidak dapat dipulihkan.');">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="cursor-pointer rounded-lg border border-danger-ink/30 px-3 py-1.5 text-xs font-semibold text-danger-ink transition hover:bg-danger-bg">Hapus</button>
                    </form>
                  @endif
                </div>
              </td>
            </tr>
            <tr id="edit-admin-{{ $account->id }}" class="hidden bg-paper">
              <td colspan="4" class="px-5 py-4">
                <form method="POST" action="{{ route('admin.accounts.update', $account) }}" class="grid gap-3 md:grid-cols-2">
                  @csrf
                  @method('PUT')
                  <label class="text-xs font-medium text-mute">Nama
                    <input name="name" value="{{ $account->name }}" required maxlength="255" class="field mt-1 text-sm">
                    @error('name') <span class="mt-1 block text-xs text-danger-ink">{{ $message }}</span> @enderror
                  </label>
                  <label class="text-xs font-medium text-mute">Email
                    <input name="email" type="email" value="{{ $account->email }}" required maxlength="255" class="field mt-1 text-sm">
                    @error('email') <span class="mt-1 block text-xs text-danger-ink">{{ $message }}</span> @enderror
                  </label>
                  <label class="text-xs font-medium text-mute">Kata sandi baru <span class="font-normal">(kosongkan jika tidak diubah)</span>
                    <input name="password" type="password" minlength="12" class="field mt-1 text-sm" autocomplete="new-password">
                    @error('password') <span class="mt-1 block text-xs text-danger-ink">{{ $message }}</span> @enderror
                  </label>
                  <label class="text-xs font-medium text-mute">Konfirmasi kata sandi baru
                    <input name="password_confirmation" type="password" minlength="12" class="field mt-1 text-sm" autocomplete="new-password">
                  </label>
                  <div class="flex flex-wrap gap-2 md:col-span-2">
                    <button type="submit" class="cursor-pointer rounded-xl border border-brand px-3.5 py-2 text-sm font-semibold text-brand transition hover:bg-tint">Simpan perubahan</button>
                    <button type="button" data-edit="edit-admin-{{ $account->id }}" class="cursor-pointer rounded-xl border border-line px-3.5 py-2 text-sm font-semibold transition hover:bg-tint">Batal</button>
                  </div>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="border-t border-line px-5 py-4">{{ $admins->links() }}</div>
  @endif
</section>
@endsection

@push('scripts')
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
  document.querySelectorAll('[data-edit]').forEach(button => {
    button.addEventListener('click', () => {
      const row = document.getElementById(button.dataset.edit);
      row.classList.toggle('hidden');
    });
  });
</script>
@endpush

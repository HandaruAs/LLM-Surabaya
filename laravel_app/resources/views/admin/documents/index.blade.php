@extends('admin.layout')

@section('title', 'Kelola dokumen')
@section('subheading', 'Unggah dokumen PDF atau Excel (.xlsx), maksimal 50 MB. Dokumen terindeks menjadi rujukan chatbot.')

@php
    // Waktu disimpan dalam zona APP_TIMEZONE (UTC), ditampilkan dalam WIB.
    $bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $tanggal = function ($value) use ($bulan) {
        $t = \Illuminate\Support\Carbon::parse($value, config('app.timezone'))->setTimezone('Asia/Jakarta');

        return $t->day.' '.$bulan[$t->month - 1].' '.$t->year.', '.$t->format('H.i');
    };
    $fileIcons = [
        'file' => \App\Support\Icons::svg('file', 'size-[18px]'),
        'sheet' => \App\Support\Icons::svg('sheet', 'size-[18px]'),
    ];
    $badge = [
        'indexed' => ['Terindeks', 'bg-success-bg text-success-ink', 'check'],
        'failed' => ['Gagal indeks', 'bg-danger-bg text-danger-ink', 'alert'],
        'pending' => ['Menunggu indeks', 'bg-warning-bg text-warning-ink', 'clock'],
    ];
@endphp

@section('content')
  @if (session('status'))
    @if (str_contains(session('status'), 'diindeks'))
      <x-alert type="success" :title="trim(session('status'))">Dokumen siap dipakai chatbot sebagai rujukan.</x-alert>
    @else
      <x-alert type="success">{{ session('status') }}</x-alert>
    @endif
  @endif
  @if (session('warning'))
    <x-alert type="warning" title="Ada yang perlu diperiksa">{{ session('warning') }}</x-alert>
  @endif
  @error('document')
    <x-alert type="danger" title="Dokumen tidak bisa diunggah">{{ $message }}</x-alert>
  @enderror

  {{-- Unggah dokumen --}}
  <section class="rounded-2xl border border-line bg-card p-5 shadow-card">
    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
      <h2 class="font-serif text-lg font-semibold">Unggah dokumen</h2>
      <span class="text-xs text-mute">PDF atau XLSX · maks. 50 MB</span>
    </div>

    <form method="POST" action="{{ route('admin.documents.store') }}" enctype="multipart/form-data" id="uploadForm"
          class="mt-4 grid gap-4 lg:grid-cols-2 lg:items-center">
      @csrf
      <label id="dropzone" class="group flex cursor-pointer flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-line bg-paper px-6 py-8 text-center transition hover:border-brand hover:bg-tint">
        <span class="grid size-12 place-items-center rounded-2xl bg-card text-brand shadow-card transition group-hover:scale-105"><x-icon name="upload" class="size-6" /></span>
        <span><span class="font-semibold text-brand">Pilih file</span> <span class="text-mute">atau seret ke sini</span></span>
        <span class="text-xs text-mute">Dokumen akan langsung diindeks agar bisa dipakai chatbot sebagai rujukan.</span>
        <input type="file" name="document" id="fileInput" required class="sr-only"
               accept=".pdf,.xlsx,application/pdf,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
      </label>

      <div class="space-y-3">
        <div class="text-xs font-semibold uppercase tracking-wider text-mute">File terpilih</div>
        <div id="picked" class="rounded-xl border border-dashed border-line p-4 text-sm text-mute">Belum ada file dipilih.</div>
        <button type="submit" id="uploadBtn" disabled
                class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-brand px-4 py-2.5 text-sm font-semibold text-paper shadow-sm transition hover:bg-brand-strong active:translate-y-px disabled:cursor-not-allowed disabled:opacity-50">
          <x-icon name="upload" class="size-4" /><span id="uploadLabel">Unggah &amp; indeks</span>
        </button>
        <div id="uploadProgress" class="hidden h-0.5 overflow-hidden rounded-full bg-line"><div class="h-full w-1/3 animate-sweep bg-brand"></div></div>
      </div>
    </form>
  </section>

  {{-- Dokumen tersimpan --}}
  <section class="overflow-hidden rounded-2xl border border-line bg-card shadow-card">
    <div class="border-b border-line px-5 py-4">
      <h2 class="font-serif text-lg font-semibold">Dokumen tersimpan</h2>
      <p class="text-xs text-mute">{{ $documents->count() }} dokumen · urut terbaru</p>
    </div>

    @if ($documents->isEmpty())
      <div class="flex flex-col items-center gap-2 px-5 py-12 text-center">
        <span class="grid size-12 place-items-center rounded-2xl bg-tint text-brand"><x-icon name="file" class="size-6" /></span>
        <p class="font-medium">Belum ada dokumen yang diunggah.</p>
        <p class="text-sm text-mute">Dokumen yang diunggah akan muncul di sini beserta status indeksnya.</p>
      </div>
    @else
      <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] text-left text-sm">
          <thead class="bg-paper text-xs font-semibold uppercase tracking-wider text-mute">
            <tr>
              <th class="py-2.5 pr-3 pl-5">Dokumen</th>
              <th class="px-3 py-2.5">Status</th>
              <th class="px-3 py-2.5">Diunggah</th>
              <th class="py-2.5 pr-5 pl-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line">
            @foreach ($documents as $document)
              @php
                  $ext = strtolower(pathinfo($document->original_name, PATHINFO_EXTENSION));
                  $isSheet = $ext === 'xlsx';
                  [$label, $tone, $icon] = $badge[$document->status] ?? $badge['pending'];
              @endphp
              <tr class="transition hover:bg-paper">
                <td class="py-3.5 pr-3 pl-5">
                  <div class="flex items-center gap-3">
                    <span @class(['grid size-10 shrink-0 place-items-center rounded-xl', 'bg-success-bg text-success-ink' => $isSheet, 'bg-danger-bg text-danger-ink' => ! $isSheet])>
                      <x-icon :name="$isSheet ? 'sheet' : 'file'" class="size-5" />
                    </span>
                    <div class="min-w-0">
                      <div class="max-w-[22rem] truncate font-medium" title="{{ $document->original_name }}">{{ $document->original_name }}</div>
                      <div class="text-xs text-mute"><span class="uppercase">{{ $ext }}</span> · {{ number_format($document->size_bytes / 1024, 1, ',', '.') }} KB</div>
                    </div>
                  </div>
                </td>
                <td class="px-3 py-3.5">
                  <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold {{ $tone }}">
                    <x-icon :name="$icon" class="size-3.5" />{{ $label }}
                  </span>
                </td>
                <td class="whitespace-nowrap px-3 py-3.5 text-sm text-mute">{{ $tanggal($document->created_at) }}</td>
                <td class="py-3.5 pr-5 pl-3">
                  <div class="flex justify-end">
                    <button type="button" title="Hapus" aria-label="Hapus {{ $document->original_name }}"
                            data-delete-url="{{ route('admin.documents.destroy', $document->id) }}"
                            data-delete-name="{{ $document->original_name }}"
                            class="inline-grid size-9 cursor-pointer place-items-center rounded-lg text-mute transition hover:bg-danger-bg hover:text-danger-ink">
                      <x-icon name="trash" class="size-4" />
                    </button>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </section>

  {{-- Dialog konfirmasi hapus --}}
  <dialog id="confirmDelete" aria-labelledby="confirmTitle"
          class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl border border-line bg-card p-0 text-ink shadow-xl">
    <form method="POST" id="deleteForm" class="p-6">
      @csrf
      @method('DELETE')
      <div class="flex gap-4">
        <span class="grid size-11 shrink-0 place-items-center rounded-full bg-danger-bg text-danger-ink"><x-icon name="trash" class="size-5" /></span>
        <div class="min-w-0">
          <h2 id="confirmTitle" class="font-serif text-lg font-semibold">Hapus dokumen?</h2>
          <p class="mt-2 text-sm leading-6 text-mute"><span id="deleteName" class="break-all font-medium text-ink"></span> dan indeksnya akan dihapus. Chatbot tidak lagi memakai dokumen ini sebagai rujukan.</p>
        </div>
      </div>
      <div class="mt-6 flex justify-end gap-2">
        <button type="button" id="cancelDelete" class="cursor-pointer rounded-xl border border-line px-4 py-2.5 text-sm font-semibold transition hover:bg-tint">Batal</button>
        <button type="submit" class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-danger-ink px-4 py-2.5 text-sm font-semibold text-card transition hover:opacity-90">
          <x-icon name="trash" class="size-4" />Hapus
        </button>
      </div>
    </form>
  </dialog>
@endsection

@push('scripts')
<script nonce="{{ Vite::cspNonce() }}">
  (() => {
    /* ---------- Unggah: pilih atau seret file ---------- */
    const form = document.getElementById('uploadForm');
    const input = document.getElementById('fileInput');
    const zone = document.getElementById('dropzone');
    const picked = document.getElementById('picked');
    const btn = document.getElementById('uploadBtn');
    const MAX = 50 * 1024 * 1024;
    const ICONS = @json($fileIcons);

    function showPicked() {
      const file = input.files[0];
      picked.innerHTML = '';
      if (!file) {
        picked.className = 'rounded-xl border border-dashed border-line p-4 text-sm text-mute';
        picked.textContent = 'Belum ada file dipilih.';
        btn.disabled = true;
        return;
      }
      const ext = (file.name.split('.').pop() || '').toLowerCase();
      const ok = ['pdf', 'xlsx'].includes(ext) && file.size <= MAX;
      const sheet = ext === 'xlsx';

      picked.className = 'flex items-center gap-3 rounded-xl border border-line p-3';
      const icon = document.createElement('span');
      icon.className = 'grid size-9 shrink-0 place-items-center rounded-lg ' + (sheet ? 'bg-success-bg text-success-ink' : 'bg-danger-bg text-danger-ink');
      icon.innerHTML = sheet ? ICONS.sheet : ICONS.file;
      const text = document.createElement('div');
      text.className = 'min-w-0 flex-1 text-sm';
      const name = document.createElement('div');
      name.className = 'truncate font-medium';
      name.textContent = file.name;
      const info = document.createElement('div');
      info.className = 'text-xs ' + (ok ? 'text-mute' : 'text-danger-ink');
      info.textContent = ok
        ? ext.toUpperCase() + ' · ' + (file.size / 1024).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' KB'
        : (file.size > MAX ? 'Ukuran melebihi 50 MB.' : 'Format harus PDF atau XLSX.');
      text.append(name, info);
      picked.append(icon, text);
      btn.disabled = !ok;
    }

    input.addEventListener('change', showPicked);
    ['dragenter', 'dragover'].forEach(type => zone.addEventListener(type, e => {
      e.preventDefault();
      zone.classList.add('border-brand', 'bg-tint');
    }));
    ['dragleave', 'drop'].forEach(type => zone.addEventListener(type, e => {
      e.preventDefault();
      zone.classList.remove('border-brand', 'bg-tint');
    }));
    zone.addEventListener('drop', e => {
      if (!e.dataTransfer.files.length) return;
      const dt = new DataTransfer();
      dt.items.add(e.dataTransfer.files[0]);
      input.files = dt.files;
      showPicked();
    });
    form.addEventListener('submit', () => {
      btn.disabled = true;
      document.getElementById('uploadLabel').textContent = 'Mengunggah & mengindeks…';
      document.getElementById('uploadProgress').classList.remove('hidden');
    });

    /* ---------- Dialog konfirmasi hapus ---------- */
    const dialog = document.getElementById('confirmDelete');
    const deleteForm = document.getElementById('deleteForm');
    document.querySelectorAll('[data-delete-url]').forEach(button => {
      button.addEventListener('click', () => {
        deleteForm.action = button.dataset.deleteUrl;
        document.getElementById('deleteName').textContent = button.dataset.deleteName;
        dialog.showModal();
      });
    });
    document.getElementById('cancelDelete').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', e => { if (e.target === dialog) dialog.close(); });
  })();
</script>
@endpush

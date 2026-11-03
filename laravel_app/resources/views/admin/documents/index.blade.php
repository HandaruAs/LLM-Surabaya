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
    $counts = [
        'total' => $documents->count(),
        'indexed' => $documents->where('status', 'indexed')->count(),
        'failed' => $documents->where('status', 'failed')->count(),
        'pending' => $documents->whereNotIn('status', ['indexed', 'failed'])->count(),
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
  @if ($errors->has('document') || $errors->has('document.*'))
    <x-alert type="danger" title="Dokumen tidak bisa diunggah">
      {{ $errors->first('document') ?: $errors->first('document.*') }}
    </x-alert>
  @endif
  @if (session('warning'))
    <x-alert type="warning" title="Sebagian dokumen gagal diproses">{{ session('warning') }}</x-alert>
  @endif

  {{-- Ringkasan status --}}
  <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    @foreach ([
        ['Total dokumen', $counts['total'], 'file', 'bg-tint text-brand'],
        ['Terindeks', $counts['indexed'], 'check', 'bg-success-bg text-success-ink'],
        ['Menunggu', $counts['pending'], 'clock', 'bg-warning-bg text-warning-ink'],
        ['Gagal indeks', $counts['failed'], 'alert', 'bg-danger-bg text-danger-ink'],
    ] as [$label, $value, $icon, $tone])
      <div data-reveal class="flex items-center gap-3 rounded-2xl border border-line bg-card p-4 shadow-card">
        <span class="grid size-11 shrink-0 place-items-center rounded-xl {{ $tone }}"><x-icon :name="$icon" class="size-5" /></span>
        <div class="min-w-0">
          <div class="text-2xl font-extrabold tabular-nums" data-count="{{ $value }}">{{ $value }}</div>
          <div class="truncate text-xs font-medium text-mute">{{ $label }}</div>
        </div>
      </div>
    @endforeach
  </div>

  {{-- Unggah dokumen --}}
  <section data-reveal class="rounded-3xl border border-line bg-card p-5 shadow-card sm:p-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="flex items-start gap-3">
        <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-tint text-brand"><x-icon name="upload" class="size-5" /></span>
        <div>
          <h2 class="font-serif text-lg font-bold">Unggah dokumen</h2>
          <p class="text-sm text-mute">Dokumen langsung diindeks dan dipakai chatbot sebagai rujukan.</p>
        </div>
      </div>
      <span class="rounded-full bg-paper px-3 py-1 text-xs font-medium text-mute">PDF / XLSX · maks. 50 MB · 20 file</span>
    </div>

    <form method="POST" action="{{ route('admin.documents.store') }}" enctype="multipart/form-data" id="uploadForm"
          class="mt-5 grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] lg:items-stretch">
      @csrf
      <label id="dropzone" class="group relative flex cursor-pointer flex-col items-center justify-center gap-3 overflow-hidden rounded-2xl border-2 border-dashed border-line bg-paper px-6 py-10 text-center transition hover:border-brand hover:bg-tint">
        <span class="pointer-events-none absolute inset-0 bg-dots opacity-60"></span>
        <span class="relative grid size-14 place-items-center rounded-2xl bg-card text-brand shadow-lift transition duration-300 group-hover:-translate-y-1 group-hover:scale-105"><x-icon name="upload" class="size-6" /></span>
        <span class="relative text-[15px]"><span class="font-semibold text-brand">Pilih file</span> <span class="text-mute">atau seret ke sini</span></span>
        <span class="relative text-xs text-mute">Bisa beberapa dokumen sekaligus. Nama yang sama akan menggantikan dokumen lama.</span>
        <input type="file" name="document[]" id="fileInput" required multiple class="sr-only"
               accept=".pdf,.xlsx,application/pdf,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
      </label>

      <div class="flex flex-col gap-3">
        <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wider text-mute">
          <span>File terpilih <span id="fileCount"></span></span>
          <button type="button" id="clearFiles" class="hidden cursor-pointer rounded-md px-1.5 py-0.5 normal-case tracking-normal hover:bg-danger-bg hover:text-danger-ink">Kosongkan</button>
        </div>
        <div id="picked" class="scroll-thin min-h-28 flex-1 space-y-2 overflow-y-auto rounded-2xl border border-dashed border-line p-3 text-sm text-mute lg:max-h-56">Belum ada file dipilih.</div>
        <button type="submit" id="uploadBtn" disabled
                class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-gradient-to-br from-brand to-brand-strong px-4 py-3 text-sm font-semibold text-paper shadow-glow transition active:translate-y-px disabled:cursor-not-allowed disabled:opacity-45 disabled:shadow-none">
          <x-icon name="upload" class="size-4" /><span id="uploadLabel">Unggah &amp; indeks</span>
        </button>
        <div id="uploadProgress" class="hidden h-1 overflow-hidden rounded-full bg-line"><div class="h-full w-1/3 animate-sweep rounded-full bg-brand"></div></div>
      </div>
    </form>
  </section>

  {{-- Dokumen tersimpan --}}
  <section data-reveal class="overflow-hidden rounded-3xl border border-line bg-card shadow-card">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4 sm:px-6">
      <div>
        <h2 class="font-serif text-lg font-bold">Dokumen tersimpan</h2>
        <p class="text-xs text-mute">{{ $documents->count() }} dokumen · urut terbaru</p>
      </div>
      @if ($documents->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2">
          <div class="flex rounded-xl border border-line bg-paper p-0.5 text-xs font-semibold" role="group" aria-label="Saring status">
            @foreach (['all' => 'Semua', 'indexed' => 'Terindeks', 'pending' => 'Menunggu', 'failed' => 'Gagal'] as $key => $label)
              <button type="button" data-filter="{{ $key }}" class="cursor-pointer rounded-lg px-2.5 py-1.5 text-mute transition aria-pressed:bg-card aria-pressed:text-ink aria-pressed:shadow-card" @if ($key === 'all') aria-pressed="true" @endif>{{ $label }}</button>
            @endforeach
          </div>
          <label class="relative">
            <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-mute"><x-icon name="search" class="size-4" /></span>
            <input type="search" id="docSearch" placeholder="Cari dokumen…" aria-label="Cari dokumen" class="field w-52 rounded-xl py-2 pl-9">
          </label>
        </div>
      @endif
    </div>

    @if ($documents->isEmpty())
      <div class="flex flex-col items-center gap-3 px-5 py-14 text-center">
        <span class="grid size-14 animate-float place-items-center rounded-2xl bg-tint text-brand"><x-icon name="file" class="size-6" /></span>
        <p class="font-semibold">Belum ada dokumen yang diunggah.</p>
        <p class="max-w-sm text-sm text-mute">Dokumen yang diunggah akan muncul di sini beserta status indeksnya.</p>
      </div>
    @else
      <div class="overflow-x-auto">
        <table class="w-full min-w-[680px] text-left text-sm">
          <thead class="bg-paper/70 text-[11px] font-semibold uppercase tracking-wider text-mute">
            <tr>
              <th class="py-3 pr-3 pl-5 sm:pl-6">Dokumen</th>
              <th class="px-3 py-3">Status</th>
              <th class="px-3 py-3">Diunggah</th>
              <th class="py-3 pr-5 pl-3 text-right sm:pr-6">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line">
            @foreach ($documents as $document)
              @php
                  $ext = strtolower(pathinfo($document->original_name, PATHINFO_EXTENSION));
                  $isSheet = $ext === 'xlsx';
                  $statusKey = in_array($document->status, ['indexed', 'failed'], true) ? $document->status : 'pending';
                  [$label, $tone, $icon] = $badge[$statusKey];
              @endphp
              <tr data-reveal-row data-doc-row data-status="{{ $statusKey }}" data-name="{{ strtolower($document->original_name) }}" class="group transition hover:bg-paper/70">
                <td class="py-3.5 pr-3 pl-5 sm:pl-6">
                  <div class="flex items-center gap-3">
                    <span @class(['grid size-10 shrink-0 place-items-center rounded-xl transition group-hover:scale-105', 'bg-success-bg text-success-ink' => $isSheet, 'bg-danger-bg text-danger-ink' => ! $isSheet])>
                      <x-icon :name="$isSheet ? 'sheet' : 'file'" class="size-5" />
                    </span>
                    <div class="min-w-0">
                      <div class="max-w-[24rem] truncate font-semibold" title="{{ $document->original_name }}">{{ $document->original_name }}</div>
                      <div class="text-xs text-mute"><span class="uppercase">{{ $ext }}</span> · {{ number_format($document->size_bytes / 1024, 1, ',', '.') }} KB</div>
                    </div>
                  </div>
                </td>
                <td class="px-3 py-3.5">
                  <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold {{ $tone }}">
                    @if ($statusKey === 'pending')
                      <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-current opacity-50"></span><span class="relative inline-flex size-2 rounded-full bg-current"></span></span>
                    @else
                      <x-icon :name="$icon" class="size-3.5" />
                    @endif
                    {{ $label }}
                  </span>
                </td>
                <td class="whitespace-nowrap px-3 py-3.5 text-sm text-mute">{{ $tanggal($document->created_at) }}</td>
                <td class="py-3.5 pr-5 pl-3 sm:pr-6">
                  <div class="flex justify-end">
                    <button type="button" title="Hapus" aria-label="Hapus {{ $document->original_name }}"
                            data-delete-url="{{ route('admin.documents.destroy', $document->id) }}"
                            data-delete-name="{{ $document->original_name }}"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl px-2.5 py-2 text-xs font-semibold text-mute transition hover:bg-danger-bg hover:text-danger-ink">
                      <x-icon name="trash" class="size-4" /><span class="hidden lg:inline">Hapus</span>
                    </button>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
        <p id="noMatch" class="hidden px-6 py-10 text-center text-sm text-mute">Tidak ada dokumen yang cocok.</p>
      </div>
    @endif
  </section>

  {{-- Dialog konfirmasi hapus --}}
  <dialog id="confirmDelete" aria-labelledby="confirmTitle"
          class="m-auto w-[calc(100%-2rem)] max-w-md rounded-3xl border border-line bg-card p-0 text-ink shadow-lift">
    <form method="POST" id="deleteForm" class="p-6">
      @csrf
      @method('DELETE')
      <span class="grid size-12 place-items-center rounded-2xl bg-danger-bg text-danger-ink"><x-icon name="trash" class="size-5" /></span>
      <h2 id="confirmTitle" class="mt-4 font-serif text-xl font-bold">Hapus dokumen?</h2>
      <p class="mt-2 text-sm leading-6 text-mute"><span id="deleteName" class="break-all font-semibold text-ink"></span> dan indeksnya akan dihapus. Chatbot tidak lagi memakai dokumen ini sebagai rujukan.</p>
      <div class="mt-6 grid grid-cols-2 gap-2">
        <button type="button" id="cancelDelete" class="cursor-pointer rounded-xl border border-line px-4 py-2.5 text-sm font-semibold transition hover:bg-tint">Batal</button>
        <button type="submit" class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-xl bg-danger-ink px-4 py-2.5 text-sm font-semibold text-card transition hover:opacity-90">
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
    const fileCount = document.getElementById('fileCount');
    const clearBtn = document.getElementById('clearFiles');
    const MAX = 50 * 1024 * 1024;
    const MAX_FILES = 20;
    const ICONS = @json($fileIcons);
    let selectedFiles = Array.from(input.files);

    function showPicked() {
      const files = Array.from(input.files);
      picked.innerHTML = '';
      fileCount.textContent = files.length ? '(' + files.length + ')' : '';
      if (!files.length) {
        picked.className = 'scroll-thin min-h-28 flex-1 space-y-2 overflow-y-auto rounded-2xl border border-dashed border-line p-3 text-sm text-mute lg:max-h-56';
        clearBtn.classList.add('hidden');
        picked.textContent = 'Belum ada file dipilih.';
        btn.disabled = true;
        return;
      }

      picked.className = 'scroll-thin min-h-28 flex-1 space-y-2 overflow-y-auto rounded-2xl border border-line p-3 lg:max-h-56';
      clearBtn.classList.remove('hidden');
      let allValid = files.length <= MAX_FILES;
      files.forEach(file => {
        const ext = (file.name.split('.').pop() || '').toLowerCase();
        const ok = ['pdf', 'xlsx'].includes(ext) && file.size <= MAX;
        allValid = allValid && ok;
        const sheet = ext === 'xlsx';
        const row = document.createElement('div');
        row.className = 'flex items-center gap-3 rounded-xl bg-paper p-2';
        const icon = document.createElement('span');
        icon.className = 'grid size-8 shrink-0 place-items-center rounded-lg ' + (sheet ? 'bg-success-bg text-success-ink' : 'bg-danger-bg text-danger-ink');
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
        row.append(icon, text);
        picked.append(row);
      });
      if (files.length > MAX_FILES) {
        const error = document.createElement('p');
        error.className = 'text-xs text-danger-ink';
        error.textContent = 'Maksimal 20 file dalam satu pengiriman.';
        picked.append(error);
      }
      btn.disabled = !allValid;
    }

    function setFiles(files, append = false) {
      const incoming = Array.from(files);
      selectedFiles = append ? [...selectedFiles, ...incoming] : incoming;
      const dt = new DataTransfer();
      selectedFiles.forEach(file => dt.items.add(file));
      input.files = dt.files;
      showPicked();
    }

    input.addEventListener('change', () => setFiles(input.files, true));
    clearBtn.addEventListener('click', () => setFiles([], false));
    ['dragenter', 'dragover'].forEach(type => zone.addEventListener(type, e => {
      e.preventDefault();
      zone.classList.add('border-brand', 'bg-tint');
    }));
    ['dragleave', 'drop'].forEach(type => zone.addEventListener(type, e => {
      e.preventDefault();
      zone.classList.remove('border-brand', 'bg-tint');
    }));
    zone.addEventListener('drop', e => {
      if (e.dataTransfer.files.length) setFiles(e.dataTransfer.files, true);
    });
    form.addEventListener('submit', () => {
      btn.disabled = true;
      document.getElementById('uploadLabel').textContent = 'Mengunggah & mengindeks…';
      document.getElementById('uploadProgress').classList.remove('hidden');
    });

    /* ---------- Saring & cari dokumen ---------- */
    const rows = Array.from(document.querySelectorAll('[data-doc-row]'));
    const search = document.getElementById('docSearch');
    const noMatch = document.getElementById('noMatch');
    let filter = 'all';
    function applyFilter() {
      const q = (search?.value || '').trim().toLowerCase();
      let shown = 0;
      rows.forEach(row => {
        const ok = (filter === 'all' || row.dataset.status === filter) && (!q || row.dataset.name.includes(q));
        row.classList.toggle('hidden', !ok);
        if (ok) shown++;
      });
      noMatch?.classList.toggle('hidden', shown > 0);
    }
    document.querySelectorAll('[data-filter]').forEach(btn => btn.addEventListener('click', () => {
      filter = btn.dataset.filter;
      document.querySelectorAll('[data-filter]').forEach(b => b.setAttribute('aria-pressed', b === btn ? 'true' : 'false'));
      applyFilter();
    }));
    search?.addEventListener('input', applyFilter);

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

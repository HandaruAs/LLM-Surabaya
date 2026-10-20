@extends('admin.layout')

@section('title', 'Konfigurasi AI')
@section('subheading', 'Atur cara chatbot menjawab. Perubahan berlaku mulai pertanyaan berikutnya. Cek hasilnya di menu Chatbot AI.')

@section('content')
<div class="max-w-4xl space-y-6">

    {{-- Peran AI --}}
    <section class="rounded-2xl border border-line bg-card p-6 shadow-sm">
        <h2 class="font-semibold">Peran AI</h2>
        <p class="mt-1 text-sm text-mute">Satu kalimat tentang siapa AI ini dan untuk siapa.</p>
        <input id="role" type="text" placeholder="Contoh: Kamu adalah asisten edukasi keuangan untuk masyarakat Surabaya."
            class="mt-3 w-full rounded-xl border border-line bg-paper p-3 text-sm placeholder:text-mute focus:border-brand focus:outline-none focus:ring-4 focus:ring-tint">
    </section>

    {{-- Aturan jawaban --}}
    <section class="rounded-2xl border border-line bg-card p-6 shadow-sm">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-semibold">Aturan jawaban</h2>
            <span id="cnt" class="rounded-lg bg-tint px-3 py-1 text-xs font-medium text-brand"></span>
        </div>
        <p class="mt-1 text-sm text-mute">Centang untuk mengaktifkan. Aturan yang tidak dicentang tetap tersimpan tapi tidak dikirim ke AI.</p>

        <div id="list" class="mt-4 divide-y divide-line overflow-hidden rounded-xl border border-line bg-paper"></div>

        <div class="mt-3 flex gap-2">
            <input id="new" type="text" placeholder="Contoh: Selalu sebut nama dinas yang relevan"
                class="min-w-0 flex-1 rounded-xl border border-line bg-paper p-3 text-sm placeholder:text-mute focus:border-brand focus:outline-none focus:ring-4 focus:ring-tint">
            <button id="add" type="button" class="shrink-0 cursor-pointer rounded-xl border border-line px-4 text-sm font-semibold hover:bg-tint focus:outline-none focus:ring-4 focus:ring-tint">Tambah aturan</button>
        </div>
        <p id="err" role="alert" class="mt-2 min-h-5 text-sm text-danger-ink"></p>
    </section>


    {{-- Bar simpan --}}
    <div class="sticky bottom-4 flex items-center gap-4 rounded-2xl border border-line bg-card p-4 shadow-sm">
        <button id="save" type="button" class="cursor-pointer rounded-xl bg-brand px-6 py-2.5 text-sm font-semibold text-paper shadow-sm hover:opacity-90 focus:outline-none focus:ring-4 focus:ring-tint">Simpan perubahan</button>
        <p id="status" role="status" aria-live="polite" class="text-sm text-mute"></p>
    </div>
</div>
@endsection

@push('scripts')
<script nonce="{{ Vite::cspNonce() }}">
(() => {
    const csrf = '{{ csrf_token() }}';
    const saveUrl = '{{ route('admin.ai-config') }}';
    const $ = (s) => document.querySelector(s);
    const state = @json($config);
    state.rules = (state.rules || []).map((r, i) => ({ id: i + 1, text: r.text, on: !!r.on }));
    let nid = state.rules.length, editing = null, confirming = null;

    const pack = () => ({
        role: (state.role || '').trim(),
        rules: state.rules.map((r) => ({ text: r.text, on: r.on })),
        top_k: Number(state.top_k),
        num_ctx: Number(state.num_ctx),
    });
    let saved = JSON.stringify(pack());
    const isDirty = () => JSON.stringify(pack()) !== saved;

    const setErr = (m) => { $('#err').textContent = m || ''; };
    const setStatus = (m, bad = false) => { const s = $('#status'); s.textContent = m; s.className = 'text-sm ' + (bad ? 'text-danger-ink' : 'text-mute'); };

    const ICON = {
        edit: '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        trash: '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/>',
        check: '<path d="M20 6 9 17l-5-5"/>',
        x: '<path d="M18 6 6 18"/><path d="M6 6l12 12"/>',
    };
    function iconBtn(label, key, fn) {
        const b = document.createElement('button');
        b.type = 'button'; b.title = label; b.setAttribute('aria-label', label);
        b.className = 'cursor-pointer rounded-lg p-2 text-mute hover:bg-tint hover:text-ink focus:outline-none focus:ring-2 focus:ring-brand';
        b.innerHTML = '<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + ICON[key] + '</svg>';
        b.onclick = fn;
        return b;
    }

    function renderList() {
        const list = $('#list'); list.innerHTML = '';
        if (!state.rules.length) {
            const e = document.createElement('p');
            e.className = 'p-4 text-sm text-mute';
            e.textContent = 'Belum ada aturan. Tambahkan aturan pertama di bawah.';
            list.appendChild(e);
        }
        state.rules.forEach((r) => {
            const row = document.createElement('div');
            row.className = 'flex min-h-14 items-center gap-3 px-4 py-2 text-sm';
            if (confirming === r.id) {
                const q = document.createElement('span'); q.className = 'flex-1'; q.textContent = 'Hapus aturan ini?';
                const y = document.createElement('button'); y.type = 'button'; y.textContent = 'Hapus';
                y.className = 'cursor-pointer rounded-lg border border-danger px-3 py-1.5 text-xs font-semibold text-danger-ink hover:bg-tint';
                y.onclick = () => { state.rules = state.rules.filter((x) => x.id !== r.id); confirming = null; refresh(); };
                const n = document.createElement('button'); n.type = 'button'; n.textContent = 'Batal';
                n.className = 'cursor-pointer rounded-lg border border-line px-3 py-1.5 text-xs font-medium hover:bg-tint';
                n.onclick = () => { confirming = null; renderList(); };
                row.append(q, y, n);
            } else if (editing === r.id) {
                const inp = document.createElement('input'); inp.type = 'text'; inp.value = r.text;
                inp.className = 'min-w-0 flex-1 rounded-lg border border-line bg-paper p-2 focus:border-brand focus:outline-none focus:ring-4 focus:ring-tint';
                const save = () => {
                    const v = inp.value.trim();
                    if (!v) { setErr('Aturan tidak boleh kosong.'); return; }
                    r.text = v; editing = null; setErr(''); refresh();
                };
                const cancel = () => { editing = null; setErr(''); renderList(); };
                inp.onkeydown = (e) => { if (e.key === 'Enter') save(); if (e.key === 'Escape') cancel(); };
                inp.oninput = () => setErr('');
                row.append(inp, iconBtn('Simpan edit', 'check', save), iconBtn('Batalkan edit', 'x', cancel));
                setTimeout(() => inp.focus(), 0);
            } else {
                const c = document.createElement('input'); c.type = 'checkbox'; c.checked = r.on;
                c.className = 'size-4 shrink-0 cursor-pointer accent-brand'; c.setAttribute('aria-label', 'Aktifkan aturan');
                c.onchange = () => { r.on = c.checked; refresh(); };
                const s = document.createElement('span'); s.textContent = r.text;
                s.className = 'min-w-0 flex-1 ' + (r.on ? '' : 'text-mute line-through');
                row.append(c, s,
                    iconBtn('Edit aturan', 'edit', () => { editing = r.id; confirming = null; setErr(''); renderList(); }),
                    iconBtn('Hapus aturan', 'trash', () => { confirming = r.id; editing = null; renderList(); }));
            }
            list.appendChild(row);
        });
    }

    function refresh() {
        renderList();
        $('#cnt').textContent = state.rules.filter((r) => r.on).length + ' dari ' + state.rules.length + ' aktif';
        if (isDirty()) setStatus('Ada perubahan yang belum disimpan.');
    }

    $('#role').value = state.role || '';
    $('#role').oninput = (e) => { state.role = e.target.value; refresh(); };

    function add() {
        const i = $('#new'), v = i.value.trim();
        if (!v) { setErr('Tulis aturannya dulu.'); return; }
        state.rules.push({ id: ++nid, text: v, on: true });
        i.value = ''; setErr(''); refresh();
    }
    $('#add').onclick = add;
    $('#new').onkeydown = (e) => { if (e.key === 'Enter') { e.preventDefault(); add(); } };
    $('#new').oninput = () => setErr('');

    $('#save').onclick = async () => {
        setStatus('Menyimpan...');
        try {
            const res = await fetch(saveUrl, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(pack()),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                setStatus(data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Gagal menyimpan.'), true);
                return;
            }
            saved = JSON.stringify(pack());
            setStatus('Tersimpan. Chatbot langsung memakai pengaturan ini.');
        } catch (e) { setStatus('Gagal menyimpan. Cek koneksi ke server Laravel.', true); }
    };

    window.addEventListener('beforeunload', (e) => { if (isDirty()) { e.preventDefault(); e.returnValue = ''; } });
    refresh(); setStatus('');
})();
</script>
@endpush
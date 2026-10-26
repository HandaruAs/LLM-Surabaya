<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
@include('partials.head')
</head>
@php
    $adminMode = $adminMode ?? false;
    $user = $adminMode ? auth('web')->user() : auth('jwt')->user();
    $loggedIn = $user !== null;
    $initials = $user
        ? (collect(preg_split('/\s+/', trim($user->name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') ?: 'AN')
        : 'AN';
    $icons = \App\Support\Icons::ALL;
    $faq = [
        ['Apa perbedaan pajak daerah dan retribusi daerah?', 'Pajak daerah adalah pungutan wajib kepada daerah oleh orang pribadi atau badan tanpa imbalan langsung, dan hasilnya dipakai untuk kepentingan daerah. Retribusi dibayar atas jasa atau perizinan tertentu yang disediakan pemerintah daerah, sehingga pembayarnya menerima imbalan langsung.'],
        ['Apa itu APBD dan mengapa penting bagi warga?', 'APBD adalah rencana keuangan tahunan pemerintah daerah yang ditetapkan bersama DPRD. Isinya mencakup pendapatan, belanja, dan pembiayaan daerah. Dari sinilah layanan publik dan pembangunan dibiayai.'],
        ['Apa itu PBB-P2 dan siapa yang wajib membayarnya?', 'PBB-P2 adalah Pajak Bumi dan Bangunan Perdesaan dan Perkotaan yang dikelola pemerintah daerah. Yang wajib membayar adalah orang pribadi atau badan yang memiliki, menguasai, atau memperoleh manfaat atas bumi dan/atau bangunan.'],
        ['Apa itu BPHTB dan kapan dibayar?', 'BPHTB adalah Bea Perolehan Hak atas Tanah dan Bangunan, yaitu pajak atas perolehan hak, misalnya karena jual beli, hibah, atau waris. Umumnya dibayar oleh pihak yang memperoleh hak. Waktu dan tata cara pembayarannya mengikuti ketentuan daerah, sebaiknya konfirmasi ke petugas.'],
        ['Bagaimana jika terlambat membayar pajak daerah?', 'Keterlambatan umumnya dikenai sanksi administratif berupa denda. Besaran dan ketentuannya diatur dalam peraturan daerah, jadi periksa ketentuan yang berlaku atau tanyakan kepada petugas.'],
    ];
@endphp
<body class="h-full bg-paper font-sans text-ink">
<div class="relative grid h-dvh grid-cols-1 md:grid-cols-[var(--side-w)_minmax(0,1fr)]">

  {{-- ================= Sidebar ================= --}}
  <aside id="side" class="fixed inset-y-0 left-0 z-30 flex w-[19rem] max-w-[88vw] -translate-x-full flex-col gap-4 overflow-y-auto border-r border-line bg-card px-4 py-5 transition-transform md:static md:w-auto md:max-w-none md:translate-x-0">
    <div class="flex items-center gap-3">
      <div class="grid size-10 place-items-center rounded-xl bg-brand text-paper shadow-sm"><x-icon name="landmark" class="size-5" /></div>
      <div>
        <div class="font-serif text-lg font-semibold leading-tight">LLM Surabaya</div>
        <div class="text-xs text-mute">Informasi keuangan daerah</div>
      </div>
    </div>

    <button id="btnNew" type="button" class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-brand px-4 py-2.5 text-sm font-semibold text-paper shadow-sm transition hover:bg-brand-strong active:translate-y-px">
      <x-icon name="newchat" class="size-4" />Obrolan baru
    </button>

    @if ($loggedIn)
    <div class="flex min-h-[8rem] flex-1 flex-col">
      <div class="flex items-center justify-between px-1 pb-1 text-xs font-semibold uppercase tracking-wider text-mute">
        <span>Riwayat</span>
        <button id="btnClear" type="button" class="cursor-pointer font-medium normal-case tracking-normal hover:text-danger-ink">Hapus semua</button>
      </div>
      <div class="relative mb-2">
        <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-mute"><x-icon name="search" class="size-4" /></span>
        <input id="histSearch" type="search" placeholder="Cari riwayat…" aria-label="Cari riwayat" class="field py-2 pl-9">
      </div>
      <nav id="hist" class="flex min-h-0 flex-1 flex-col gap-0.5 overflow-y-auto" aria-label="Riwayat percakapan"></nav>
    </div>
    @else
    {{-- Tamu: riwayat tidak disimpan. --}}
    <div class="flex flex-1 flex-col gap-3">
      <div class="rounded-2xl border border-line p-4">
        <div class="flex items-center gap-2 text-sm font-semibold"><x-icon name="clock" class="size-4 text-gold" />Riwayat tidak disimpan</div>
        <p class="mt-1.5 text-sm leading-6 text-mute">Kamu belum masuk. Percakapan ini akan hilang saat halaman ditutup atau dimuat ulang. Masuk atau daftar lewat tombol di kanan atas agar riwayat tersimpan.</p>
      </div>
    </div>
    @endif

    @if ($adminMode)
    <a href="{{ route('admin.dashboard') }}" class="flex items-center justify-between rounded-xl border border-brand px-3.5 py-2.5 text-sm font-medium text-brand transition hover:bg-tint">
      Kembali ke area admin <x-icon name="arrow" class="size-4" />
    </a>
    @endif

    <div class="space-y-3 border-t border-line pt-3">
      <button id="btnFaq" type="button" class="flex w-full cursor-pointer items-center justify-between rounded-xl px-3 py-2 text-sm font-medium transition hover:bg-tint">
        <span class="flex items-center gap-2.5"><x-icon name="help" class="size-4 text-brand" />FAQ</span>
        <x-icon name="chevron" class="size-4 text-mute" />
      </button>

      <div class="rounded-2xl border border-line bg-tint p-4">
        <div class="text-sm font-semibold">Hubungi kami</div>
        <ul class="mt-3 space-y-2.5 text-sm text-mute">
          <li class="flex gap-2.5"><x-icon name="phone" class="mt-0.5 size-3.5 text-brand" />
            <div class="flex flex-col"><a class="hover:text-ink hover:underline" href="tel:+62315321444">(031) 5321444</a><a class="hover:text-ink hover:underline" href="tel:+623199277339">(031) 99277339</a></div></li>
          <li class="flex gap-2.5"><x-icon name="mail" class="mt-0.5 size-3.5 text-brand" />
            <div class="flex min-w-0 flex-col"><a class="break-all hover:text-ink hover:underline" href="mailto:dinkominfo@surabaya.go.id">dinkominfo@surabaya.go.id</a><a class="break-all hover:text-ink hover:underline" href="mailto:mediacenter@surabaya.go.id">mediacenter@surabaya.go.id</a></div></li>
          <li class="flex gap-2.5"><x-icon name="pin" class="mt-0.5 size-3.5 text-brand" />
            <address class="not-italic leading-relaxed">Jl. Jimerto No. 25-27, Ketabang, Kec. Genteng, Surabaya 60272</address></li>
        </ul>
      </div>

    </div>
  </aside>
  <div id="scrim" class="fixed inset-0 z-20 hidden bg-scrim md:hidden"></div>

  <div id="resizer" role="separator" aria-orientation="vertical" aria-label="Ubah lebar sidebar"
       class="absolute inset-y-0 z-20 hidden w-1.5 cursor-col-resize touch-none transition-colors hover:bg-brand/40 active:bg-brand/60 md:left-[calc(var(--side-w)-3px)] md:block"></div>

  {{-- ================= Area percakapan ================= --}}
  <section class="flex min-h-0 min-w-0 flex-col">
    <header class="relative flex items-center gap-3 border-b border-line bg-card px-4 py-3.5 md:px-6">
      <button id="btnMenu" type="button" aria-label="Buka menu" class="inline-grid size-9 cursor-pointer place-items-center rounded-lg text-mute transition hover:bg-tint hover:text-ink md:hidden"><x-icon name="menu" class="size-5" /></button>
      <div class="min-w-0 flex-1">
        <h1 id="title" class="truncate font-serif text-lg font-semibold">Obrolan baru</h1>
        <p id="subtitle" class="truncate text-xs text-mute">Tanya jawab keuangan daerah Kota Surabaya</p>
      </div>
      @if ($loggedIn)
      <div class="flex shrink-0 items-center gap-2 sm:gap-3">
        <div class="hidden items-center gap-2.5 sm:flex">
          <span class="grid size-8 shrink-0 place-items-center rounded-full bg-tint text-xs font-bold text-brand">{{ $initials }}</span>
          <div class="max-w-[12rem] leading-tight">
            <div class="truncate text-sm font-semibold">{{ $user->name }}</div>
            <div class="truncate text-xs text-mute">{{ $user->email }}</div>
          </div>
        </div>
        <form method="POST" action="{{ $adminMode ? route('admin.logout') : route('logout') }}">
          @csrf
          <button type="submit" class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-line px-3 py-2 text-sm font-semibold text-mute transition hover:border-danger hover:text-danger-ink">
            <x-icon name="logout" class="size-4" />Keluar
          </button>
        </form>
      </div>
      @elseif (! $adminMode)
      <div class="flex shrink-0 items-center gap-2">
        <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-line px-3 py-2 text-sm font-semibold transition hover:bg-tint"><x-icon name="login" class="size-4" />Masuk</a>
        <a href="{{ route('register') }}" class="rounded-xl bg-brand px-3 py-2 text-sm font-semibold text-paper shadow-sm transition hover:bg-brand-strong">Daftar</a>
      </div>
      @endif
      <span class="absolute -bottom-px left-0 h-[3px] w-16 bg-gold"></span>
    </header>

    <div id="stream" class="flex-1 overflow-y-auto px-4 py-8 md:px-8">
      <div id="col" class="mx-auto flex max-w-content flex-col gap-10"></div>
    </div>

    <div class="px-4 pt-2 pb-4 md:px-8 md:pb-5">
      <div class="mx-auto max-w-content">
        <p id="formErr" role="alert" class="mb-2 hidden rounded-xl bg-danger-bg px-3 py-2 text-sm text-danger-ink"></p>
        <div class="rounded-2xl border border-line bg-card p-2 shadow-card transition focus-within:border-brand focus-within:ring-4 focus-within:ring-tint">
          <textarea id="input" rows="2" aria-label="Pertanyaan" placeholder="Tulis pertanyaan Anda tentang pajak, anggaran, atau retribusi"
                    class="block max-h-40 w-full resize-none border-0 bg-transparent px-2 py-1.5 text-[15px] leading-6 text-ink outline-none placeholder:text-mute"></textarea>
          <div class="flex items-center justify-between gap-2 px-1 pt-1">
            <div id="chips" class="flex flex-wrap gap-1.5"></div>
            <div class="flex items-center gap-3">
              <span class="hidden text-xs text-mute sm:inline"><kbd class="rounded border border-line px-1 font-sans">Enter</kbd> kirim · <kbd class="rounded border border-line px-1 font-sans">Shift</kbd>+<kbd class="rounded border border-line px-1 font-sans">Enter</kbd> baris baru</span>
              <button id="btnSend" type="button" aria-label="Kirim" class="grid size-10 cursor-pointer place-items-center rounded-xl bg-brand text-paper shadow-sm transition hover:bg-brand-strong disabled:cursor-wait disabled:opacity-50"><x-icon name="send" class="size-5" /></button>
            </div>
          </div>
        </div>
        <p class="mt-2 text-center text-xs leading-5 text-mute">
          Informasi bersifat panduan umum. Untuk keputusan penting, pastikan ke instansi resmi. Jangan menuliskan NIK atau nomor rekening.
          @if ($loggedIn) Riwayat tersimpan di akunmu. @else Riwayat tidak disimpan karena kamu belum masuk. @endif
        </p>
      </div>
    </div>
  </section>
</div>

{{-- ================= FAQ ================= --}}
<dialog id="faq" aria-labelledby="faqTitle" class="m-auto w-[calc(100%-2rem)] max-w-xl overflow-hidden rounded-2xl border border-line bg-card p-0 text-ink shadow-xl">
  <div class="flex max-h-[85dvh] flex-col">
    <div class="flex items-center gap-3 border-b border-line px-5 py-4">
      <span class="grid size-10 place-items-center rounded-xl bg-tint text-brand"><x-icon name="help" class="size-5" /></span>
      <h2 id="faqTitle" class="flex-1 font-serif text-lg font-semibold">Pertanyaan yang sering diajukan</h2>
      <button type="button" data-close aria-label="Tutup" class="inline-grid size-9 cursor-pointer place-items-center rounded-lg text-mute transition hover:bg-tint hover:text-ink"><x-icon name="x" class="size-5" /></button>
    </div>
    <div class="overflow-y-auto px-5 py-2">
      @foreach ($faq as [$q, $a])
        <details class="group border-b border-line py-3 last:border-0" @if ($loop->first) open @endif>
          <summary class="flex cursor-pointer list-none items-center justify-between gap-3 font-serif text-[17px] font-semibold [&::-webkit-details-marker]:hidden">
            <span>{{ $q }}</span>
            <span class="grid size-8 shrink-0 place-items-center rounded-full bg-tint text-brand"><x-icon name="chevron" class="size-4 transition group-open:rotate-90" /></span>
          </summary>
          <p class="mt-2 pb-1 leading-7 text-mute">{{ $a }}</p>
        </details>
      @endforeach
    </div>
  </div>
</dialog>

{{-- ================= Dialog konfirmasi ================= --}}
<dialog id="confirm" aria-labelledby="confirmTitle" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl border border-line bg-card p-0 text-ink shadow-xl">
  <div class="p-6">
    <div class="flex gap-4">
      <span class="grid size-11 shrink-0 place-items-center rounded-full bg-danger-bg text-danger-ink"><x-icon name="trash" class="size-5" /></span>
      <div class="min-w-0">
        <h2 id="confirmTitle" class="font-serif text-lg font-semibold"></h2>
        <p id="confirmText" class="mt-2 text-sm leading-6 text-mute"></p>
      </div>
    </div>
    <div class="mt-6 flex justify-end gap-2">
      <button type="button" data-close class="cursor-pointer rounded-xl border border-line px-4 py-2.5 text-sm font-semibold transition hover:bg-tint">Batal</button>
      <button type="button" id="confirmOk" class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-danger-ink px-4 py-2.5 text-sm font-semibold text-card transition hover:opacity-90"><x-icon name="trash" class="size-4" />Hapus</button>
    </div>
  </div>
</dialog>

<script nonce="{{ Vite::cspNonce() }}">
/* ---------- Koneksi ke API backend (laravel_app/API.md) ---------- */
const ADMIN_MODE = @json($adminMode);
const API = ADMIN_MODE ? @json(url('/admin/ai-chat')) : @json(url('/api'));
const FB_KEY = 'llm-surabaya-feedback';
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

// Login memakai JWT di cookie. Tamu boleh bertanya, tapi riwayatnya tidak disimpan:
// percakapan tamu hanya ada di memori halaman ini dan dikirim ulang sebagai "history".
const LOGGED_IN = @json($loggedIn);
const USER_NAME = @json($user?->name);
const ICONS = @json($icons);

// Panggil API. Selalu mengembalikan { ok, status, data } — status 0 berarti server tidak bisa dihubungi.
async function api(path, method = 'GET', body){
  try {
    if (ADMIN_MODE) path = path.replace(/^\/sessions/, '/sessions').replace(/^\/chat$/, '/messages');
    const res = await fetch(API + path, {
      method,
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      body: body === undefined ? undefined : JSON.stringify(body)
    });
    const data = res.status === 204 ? {} : await res.json().catch(() => ({}));
    return { ok: res.ok, status: res.status, data };
  } catch(e){
    return { ok: false, status: 0, data: {} };
  }
}

const MSG = {
  offline: 'Tidak bisa terhubung ke server. Periksa koneksi lalu coba lagi.',
  aiDown: 'Layanan AI sedang tidak tersedia. Pertanyaan Anda sudah tersimpan, silakan kirim ulang.',
  unanswered: 'Pertanyaan ini belum terjawab.',
  generic: 'Terjadi kesalahan. Silakan coba lagi.'
};

const TOPICS = [];

// Chip di kolom tulis: mengisi contoh pertanyaan.
const CHIPS = [
  ['APBD 2025', 'Berapa total pendapatan dalam APBD Kota Surabaya tahun 2025?'],
  ['Pajak daerah', 'Apa saja jenis pajak daerah di Kota Surabaya?']
];

const $ = id => document.getElementById(id);
const col = $('col'), stream = $('stream'), input = $('input'), hist = $('hist');
let sessions = [];     // [{ id, title, updated_at }] dari GET /api/sessions
let activeId = null;   // id sesi (angka) atau null untuk obrolan baru yang belum dikirim
const msgs = {};       // id sesi -> [{ id, role, content, sources, t }]
let draft = [];        // pesan obrolan baru sebelum server membuat sesinya
const errors = {};     // id sesi -> pesan error untuk pertanyaan terakhir yang belum terjawab
let busy = false;
let loading = false;
let histQuery = '';

// Judul lengkap per sesi (pertanyaan pertama), disimpan di browser.
// Judul dari server dipotong 40 karakter, jadi sidebar memakai yang ini.
const TITLE_KEY = 'llm-surabaya-titles';
let fullTitles = {};
try { fullTitles = JSON.parse(localStorage.getItem(TITLE_KEY)) || {}; } catch(e){}
function saveTitles(){ try { localStorage.setItem(TITLE_KEY, JSON.stringify(fullTitles)); } catch(e){} }
function setFullTitle(id, text){
  if(!text || fullTitles[id] === text) return;
  fullTitles[id] = text;
  saveTitles();
}
function titleOf(c){ return fullTitles[c.id] || c.title; }

// Untuk riwayat yang judulnya terlihat terpotong, ambil pertanyaan pertamanya
// sekali saja lalu simpan, jadi tidak ada request ulang di kunjungan berikutnya.
let fillingTitles = false;
async function fillTitles(){
  if(fillingTitles) return;
  fillingTitles = true;
  try {
    for(const s of sessions.slice()){
      if(fullTitles[s.id] || !s.title || s.title.length < 36) continue;
      const r = await api('/sessions/' + s.id + '/messages');
      if(!r.ok) continue;
      const first = (r.data.data || []).find(m => m.role === 'user');
      if(first){ setFullTitle(s.id, first.content); renderHist(); }
    }
  } finally { fillingTitles = false; }
}

// Judul di header: maksimal 40 karakter. Kalau batasnya jatuh di tengah kata,
// mundur ke kata sebelumnya, lalu tambahkan titik-titik.
function shortTitle(text, max = 40){
  text = (text || '').replace(/\s+/g, ' ').trim();
  if(text.length <= max) return text;
  let cut = text.slice(0, max);
  if(text[max] !== ' '){
    const i = cut.lastIndexOf(' ');
    if(i > 0) cut = cut.slice(0, i);
  }
  return cut.replace(/[\s,.;:!?-]+$/, '') + '…';
}

function el(tag, cls, text){
  const n = document.createElement(tag);
  if(cls) n.className = cls;
  if(text !== undefined) n.textContent = text;
  return n;
}
function svg(name, cls){ return '<svg class="' + (cls || 'size-4') + ' shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (ICONS[name] || '') + '</svg>'; }
function ic(name, cls){ const s = el('span', 'inline-flex shrink-0'); s.innerHTML = svg(name, cls); return s; }
function active(){ return sessions.find(s => s.id === activeId); }
function current(){ return activeId === null ? draft : (msgs[activeId] || []); }
function fromApi(m){ return { id: m.id, role: m.role, content: m.content, sources: m.sources || [], t: Date.parse(m.created_at) || Date.now() }; }

// Suka / tidak suka hanya disimpan di browser, per id pesan.
function fbAll(){ try { return JSON.parse(localStorage.getItem(FB_KEY)) || {}; } catch(e){ return {}; } }
function fbGet(id){ return fbAll()[id] || ''; }
function fbSet(id, v){ const all = fbAll(); if(v) all[id] = v; else delete all[id]; try { localStorage.setItem(FB_KEY, JSON.stringify(all)); } catch(e){} }

function showFormErr(text){ const p = $('formErr'); p.textContent = text || ''; p.classList.toggle('hidden', !text); }
function setBusy(v){ busy = v; $('btnSend').disabled = v; }

function hm(ts){ return new Date(ts).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }); }
function dayLabel(iso){
  const ts = Date.parse(iso);
  if(!ts) return '';
  const d = new Date(ts), now = new Date();
  const diff = Math.round((new Date(now.getFullYear(), now.getMonth(), now.getDate()) - new Date(d.getFullYear(), d.getMonth(), d.getDate())) / 86400000);
  if(diff === 0) return 'Hari ini';
  if(diff === 1) return 'Kemarin';
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
}
function greet(){
  const h = new Date().getHours();
  const timeGreet = h < 11 ? 'Selamat pagi' : h < 15 ? 'Selamat siang' : h < 18 ? 'Selamat sore' : 'Selamat malam';
  return USER_NAME ? `${timeGreet} ${USER_NAME}` : timeGreet;
}

// Nama sumber untuk ditampilkan: buang awalan internal dokumen admin ("admin:5:").
function sourceName(s){
  let name = typeof s === 'string' ? s : (s && (s.title || s.source || s.filename || s.file || s.url)) || String(s);
  return name.replace(/^admin:\d+:/, '');
}
function sourceType(name){
  const ext = (name.split('.').pop() || '').toLowerCase();
  return ext === 'xlsx' ? 'Excel' : ext ? ext.toUpperCase() : 'Dokumen';
}

/* ---------- Riwayat (khusus user login) ---------- */
async function refreshSessions(){
  if(!LOGGED_IN) return true; // tamu tidak punya riwayat tersimpan
  const r = await api('/sessions');
  if(r.ok){
    sessions = r.data.data || [];
    // Buang judul tersimpan milik sesi yang sudah tidak ada.
    const ids = new Set(sessions.map(s => String(s.id)));
    Object.keys(fullTitles).forEach(k => { if(!ids.has(k)) delete fullTitles[k]; });
    saveTitles();
  }
  renderHist();
  if(r.ok) fillTitles();
  return r.ok;
}

// Sesi baru dari server: pindahkan draf ke sesi itu dan taruh di paling atas riwayat.
function adopt(id, title){
  if(activeId === null){ msgs[id] = draft; draft = []; activeId = id; }
  sessions = [{ id, title: title || 'Obrolan baru', updated_at: new Date().toISOString() }, ...sessions.filter(s => s.id !== id)];
  const firstQ = (msgs[id] || []).find(m => m.role === 'user');
  if(firstQ && !fullTitles[id]) setFullTitle(id, firstQ.content);
}

function newChat(){ if(busy) return; activeId = null; draft = []; showFormErr(''); render(); closeMenu(); input.focus(); }

async function openChat(id){
  if(busy) return;
  activeId = id; showFormErr(''); closeMenu();
  if(!msgs[id]){
    loading = true; render();
    const r = await api('/sessions/' + id + '/messages');
    loading = false;
    if(r.status === 404){ await refreshSessions(); activeId = null; render(); return; }
    if(!r.ok){ showFormErr(r.status === 0 ? MSG.offline : MSG.generic); render(); return; }
    msgs[id] = (r.data.data || []).map(fromApi);
    const firstQ = msgs[id].find(m => m.role === 'user');
    if(firstQ) setFullTitle(id, firstQ.content);
  }
  render();
}

async function delChat(id){
  if(busy) return;
  const s = sessions.find(x => x.id === id);
  if(!(await confirmBox('Hapus percakapan?', '"' + (s ? shortTitle(titleOf(s), 60) : 'Percakapan ini') + '" beserta seluruh pesannya akan dihapus dari riwayat.'))) return;
  const r = await api('/sessions/' + id, 'DELETE');
  if(!r.ok && r.status !== 404){ showFormErr(r.status === 0 ? MSG.offline : MSG.generic); return; }
  sessions = sessions.filter(s => s.id !== id);
  delete msgs[id]; delete errors[id];
  delete fullTitles[id]; saveTitles();
  if(activeId === id) activeId = null;
  render();
}

async function clearAll(){
  if(busy || !sessions.length) return;
  if(!(await confirmBox('Hapus semua riwayat?', 'Seluruh ' + sessions.length + ' percakapan di akunmu akan dihapus dan tidak bisa dikembalikan.'))) return;
  const r = await api('/sessions', 'DELETE');
  if(!r.ok){ showFormErr(r.status === 0 ? MSG.offline : MSG.generic); return; }
  sessions = []; activeId = null; draft = [];
  Object.keys(msgs).forEach(k => delete msgs[k]);
  Object.keys(errors).forEach(k => delete errors[k]);
  fullTitles = {}; saveTitles();
  render();
}

function renderHist(){
  if(!hist) return; // tamu: panel riwayat tidak ada
  hist.innerHTML = '';
  const q = histQuery.trim().toLowerCase();
  const list = q ? sessions.filter(s => (titleOf(s) || '').toLowerCase().includes(q)) : sessions;
  if(!list.length){ hist.append(el('div', 'px-3 py-3 text-sm text-mute', q ? 'Tidak ada riwayat yang cocok.' : 'Belum ada riwayat.')); return; }
  list.forEach(c => {
    const on = c.id === activeId;
    const row = el('div', 'group relative flex items-center rounded-xl transition ' + (on ? 'bg-tint text-brand' : 'text-ink hover:bg-tint'));
    if(on) row.append(el('span', 'absolute inset-y-2 left-0 w-[3px] rounded-full bg-brand'));
    const open = el('button', 'flex min-w-0 flex-1 cursor-pointer items-center gap-2.5 px-3 py-2 text-left');
    open.type = 'button';
    const text = el('span', 'min-w-0');
    text.append(el('span', 'line-clamp-2 break-words text-sm font-medium', titleOf(c)), el('span', 'block text-xs text-mute', dayLabel(c.updated_at)));
    open.append(ic('message', 'size-4 ' + (on ? 'text-brand' : 'text-mute')), text);
    open.onclick = () => openChat(c.id);
    const del = el('button', 'mr-1.5 cursor-pointer rounded-md p-1.5 text-mute transition hover:text-danger focus-visible:opacity-100 ' + (on ? '' : 'md:opacity-0 md:group-hover:opacity-100'));
    del.type = 'button';
    del.setAttribute('aria-label', 'Hapus percakapan');
    del.append(ic('trash'));
    del.onclick = () => delChat(c.id);
    row.append(open, del);
    hist.append(row);
  });
}

/* ---------- Isi percakapan ---------- */
function actionBtn(icon, label, on, onclick){
  const b = el('button', 'inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-medium transition ' + (on ? 'bg-tint text-brand' : 'text-mute hover:bg-tint hover:text-ink'));
  b.type = 'button';
  if(on) b.setAttribute('aria-pressed', 'true');
  b.append(ic(icon), document.createTextNode(label));
  b.onclick = () => onclick(b);
  return b;
}

function questionHeader(q){
  const h = el('header', 'lg:col-span-2');
  const box = el('div', 'min-w-0');
  box.append(el('h2', 'font-serif text-xl font-semibold leading-snug break-words', q.content));
  box.append(el('time', 'text-xs text-mute', 'Anda' + (q.t ? ' · ' + hm(q.t) : '')));
  h.append(box);
  return h;
}

function loadingCard(){
  const c = el('div', 'rounded-2xl border border-line bg-card p-5 shadow-card lg:col-span-2');
  c.setAttribute('role', 'status');
  const top = el('div', 'flex items-center gap-2 text-sm font-medium text-mute');
  const dot = el('span', 'relative flex size-2');
  dot.append(el('span', 'absolute inline-flex size-full animate-ping rounded-full bg-brand opacity-60'), el('span', 'relative inline-flex size-2 rounded-full bg-brand'));
  top.append(dot, document.createTextNode('Menyusun jawaban…'));
  const sk = el('div', 'mt-4 space-y-2.5 animate-pulse');
  ['w-11/12', 'w-9/12', 'w-10/12'].forEach(w => sk.append(el('div', 'h-3 rounded-full bg-tint ' + w)));
  const bar = el('div', 'mt-4 h-0.5 w-44 overflow-hidden rounded-full bg-line');
  bar.append(el('div', 'h-full w-1/3 animate-sweep bg-brand'));
  c.append(top, sk, bar);
  return c;
}

function renderEntry(q, a, last){
  const e = el('article', 'grid gap-x-8 gap-y-4 lg:grid-cols-[minmax(0,1fr)_15rem]');
  e.append(questionHeader(q));

  if(!a){
    if(busy && last){
      e.append(loadingCard());
    } else if(last && activeId !== null){
      // Tersimpan di server tapi belum dijawab: tawarkan kirim ulang (POST /api/sessions/{id}/retry).
      const box = el('div', 'flex flex-wrap items-center gap-3 rounded-2xl bg-danger-bg px-4 py-3 text-danger-ink lg:col-span-2');
      box.append(ic('alert', 'size-5'), el('span', 'flex-1 text-sm', errors[activeId] || MSG.unanswered));
      const again = el('button', 'cursor-pointer rounded-lg border border-current px-3 py-1.5 text-sm font-semibold hover:opacity-80', 'Kirim ulang');
      again.type = 'button';
      again.onclick = retry;
      box.append(again);
      e.append(box);
    } else {
      e.append(el('div', 'text-sm text-mute lg:col-span-2', 'Tidak terjawab.'));
    }
    return e;
  }

  const list = (Array.isArray(a.sources) ? a.sources : (a.sources ? [a.sources] : [])).map(sourceName);
  const card = el('div', 'min-w-0 rounded-2xl border border-line bg-card p-5 shadow-card' + (list.length ? '' : ' lg:col-span-2'));
  const label = el('div', 'mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-brand');
  label.append(ic('landmark'), document.createTextNode('Jawaban'));
  card.append(label);
  card.append(el('div', 'max-w-measure font-serif text-[17px] leading-[1.75] break-words whitespace-pre-wrap', a.content));

  const acts = el('div', 'mt-4 flex flex-wrap items-center gap-1 border-t border-line pt-3');
  acts.append(actionBtn('copy', 'Salin', false, b => {
    navigator.clipboard.writeText(a.content).then(() => {
      b.lastChild.textContent = 'Tersalin';
      setTimeout(() => { b.lastChild.textContent = 'Salin'; }, 1500);
    }).catch(() => {});
  }));
  if(a.id){
    const fb = fbGet(a.id);
    acts.append(actionBtn('up', 'Membantu', fb === 'up', () => { fbSet(a.id, fb === 'up' ? '' : 'up'); render(false); }));
    acts.append(actionBtn('down', 'Kurang membantu', fb === 'down', () => { fbSet(a.id, fb === 'down' ? '' : 'down'); render(false); }));
  }
  card.append(acts);
  e.append(card);

  if(list.length){
    const side = el('aside', 'space-y-2');
    side.append(el('div', 'text-xs font-semibold uppercase tracking-wider text-mute', 'Rujukan'));
    list.forEach((name, i) => {
      const item = el('div', 'flex gap-3 rounded-xl border border-line bg-card p-3');
      item.append(el('span', 'grid size-6 shrink-0 place-items-center rounded-md bg-tint text-[11px] font-bold text-brand', String(i + 1)));
      const t = el('span', 'min-w-0 text-sm leading-5');
      t.append(el('span', 'block break-words font-medium', name), el('span', 'text-xs text-mute', sourceType(name)));
      item.append(t);
      side.append(item);
    });
    e.append(side);
  }

  return e;
}

function renderWelcome(){
  const w = el('div', 'mx-auto w-full max-w-3xl');
  const date = new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
  const pill = el('div', 'inline-flex items-center gap-2 rounded-full border border-line bg-card px-3 py-1 text-xs font-medium text-mute');
  pill.append(el('span', 'size-1.5 rounded-full bg-gold'), document.createTextNode(date));
  w.append(pill);
  const h = el('h2', 'mt-4 font-serif text-3xl font-semibold leading-tight tracking-tight md:text-4xl');
  h.append(document.createTextNode(ADMIN_MODE ? greet() + ', ada yang bisa dibantu?' : greet() + '. Apa yang ingin Anda ketahui?'));
  w.append(h);
  w.append(el('div', 'my-5 h-[3px] w-14 rounded-full bg-gold'));
  w.append(el('p', 'max-w-[60ch] text-mute', 'Tanyakan seputar pajak daerah, anggaran, dan retribusi Kota Surabaya. Jawaban dilengkapi rujukan agar mudah ditelusuri.'));
  if(!TOPICS.length) return w;
  const grid = el('div', 'mt-8 grid gap-3 sm:grid-cols-2');
  TOPICS.forEach(tp => {
    const b = el('button', 'group flex cursor-pointer flex-col gap-3 rounded-2xl border border-line bg-card p-4 text-left shadow-card transition hover:-translate-y-0.5 hover:border-brand hover:shadow-lift');
    b.type = 'button';
    const top = el('div', 'flex items-center justify-between');
    const i = el('span', 'grid size-10 place-items-center rounded-xl bg-tint text-brand');
    i.innerHTML = svg(tp.icon, 'size-5');
    top.append(i, ic('arrow', 'size-4 text-mute transition group-hover:translate-x-0.5 group-hover:text-brand'));
    const txt = el('div');
    txt.append(el('div', 'font-serif text-[17px] font-semibold leading-snug', tp.title), el('div', 'mt-1 text-sm leading-5 text-mute', tp.q));
    b.append(top, txt);
    b.onclick = () => send(tp.q);
    grid.append(b);
  });
  w.append(grid);
  return w;
}

function render(scroll = true){
  renderHist();
  const c = active();
  const ms = current();
  const questions = ms.filter(m => m.role === 'user').length;
  $('title').textContent = c ? shortTitle(titleOf(c)) : (ms.length ? shortTitle(ms[0].content) : 'Obrolan baru');
  const firstSource = ms.find(m => m.role === 'assistant' && m.sources && m.sources.length);
  $('subtitle').textContent = questions
    ? questions + ' pertanyaan' + (firstSource ? ' · rujukan: ' + sourceName(firstSource.sources[0]) : '')
    : 'Tanya jawab keuangan daerah Kota Surabaya';
  col.innerHTML = '';
  if(!ms.length) scroll = false; // halaman sambutan dibaca dari atas
  if(loading){ col.append(el('div', 'text-sm text-mute', 'Memuat percakapan…')); }
  else if(!ms.length){ col.append(renderWelcome()); }
  else {
    ms.forEach((m, i) => {
      if(m.role !== 'user') return;
      const a = ms[i + 1] && ms[i + 1].role === 'assistant' ? ms[i + 1] : null;
      // "last" = entri ini adalah pesan paling akhir di sesi (hanya itu yang bisa dikirim ulang).
      col.append(renderEntry(m, a, (a ? i + 1 : i) === ms.length - 1));
    });
  }
  if(scroll) scrollDown(); else if(!ms.length) stream.scrollTop = 0;
}
function scrollDown(){ stream.scrollTop = stream.scrollHeight; }

// Kirim pertanyaan: POST /api/chat.
// - User login: server menyimpan pertanyaan, bertanya ke AI, lalu menyimpan jawabannya.
// - Tamu: server hanya meneruskan ke AI; riwayat obrolan dikirim dari halaman ini.
async function send(raw){
  const text = (raw || '').trim();
  if(!text || busy) return;
  showFormErr('');
  const list = current();
  const history = LOGGED_IN ? undefined : list
    .filter(m => !m.error && (m.role === 'user' || m.role === 'assistant'))
    .slice(-20)
    .map(m => ({ role: m.role, content: m.content }));
  list.push({ role: 'user', content: text, t: Date.now() });
  input.value = '';
  autosize();
  setBusy(true);
  render();

  const r = await api('/chat', 'POST', LOGGED_IN
    ? { session_id: activeId, message: text }
    : { message: text, history });

  if(r.data && r.data.session_id) adopt(r.data.session_id, r.data.title);

  if(r.ok){
    list.push(fromApi(r.data.message));
    delete errors[activeId];
  } else if(r.status === 503 && r.data.session_id){
    // Pertanyaan sudah tersimpan, AI belum menjawab -> tombol "Kirim ulang".
    errors[activeId] = MSG.aiDown;
  } else {
    // 422 / 429 / 404 / 419 / tamu gagal / server tidak terjangkau: pertanyaan TIDAK tersimpan,
    // jadi hapus dari layar dan kembalikan teksnya ke kolom input.
    list.pop();
    input.value = text;
    autosize();
    const fieldErr = r.data.errors && Object.values(r.data.errors)[0];
    showFormErr(r.status === 0 ? MSG.offline : (fieldErr && fieldErr[0]) || r.data.message || MSG.generic);
    if(r.status === 404){ activeId = null; draft = []; await refreshSessions(); }
  }

  setBusy(false);
  render();
  input.focus();
}

// Kirim ulang pertanyaan terakhir yang belum terjawab: POST /api/sessions/{id}/retry.
async function retry(){
  if(busy || activeId === null) return;
  const id = activeId;
  showFormErr('');
  setBusy(true);
  render();

  const r = await api('/sessions/' + id + '/retry', 'POST');

  if(r.ok){
    (msgs[id] || []).push(fromApi(r.data.message));
    delete errors[id];
    sessions = [...sessions.filter(s => s.id === id), ...sessions.filter(s => s.id !== id)];
  } else if(r.status === 422 || r.status === 409){
    // Sudah terjawab / sedang diproses di tab lain: muat ulang pesan dari server.
    delete msgs[id]; delete errors[id];
    setBusy(false);
    return openChat(id);
  } else if(r.status === 404){
    delete msgs[id]; activeId = null; await refreshSessions();
  } else {
    errors[id] = r.status === 0 ? MSG.offline : r.status === 503 ? MSG.aiDown : (r.data.message || MSG.generic);
  }

  setBusy(false);
  render();
}

/* ---------- Dialog ---------- */
// Konfirmasi dengan dialog sendiri (bukan confirm() bawaan browser). Mengembalikan Promise<boolean>.
function confirmBox(title, text){
  const d = $('confirm');
  $('confirmTitle').textContent = title;
  $('confirmText').textContent = text;
  return new Promise(resolve => {
    const done = v => { d.onclose = null; $('confirmOk').onclick = null; d.close(); resolve(v); };
    $('confirmOk').onclick = () => done(true);
    d.onclose = () => resolve(false);
    d.showModal();
  });
}
document.querySelectorAll('dialog').forEach(d => {
  d.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => d.close()));
  d.addEventListener('click', e => { if(e.target === d) d.close(); });
});

function autosize(){
  input.style.height = 'auto';
  input.style.height = Math.min(input.scrollHeight, 160) + 'px';
}
function openMenu(){ $('side').classList.remove('-translate-x-full'); $('scrim').classList.remove('hidden'); }
function closeMenu(){ $('side').classList.add('-translate-x-full'); $('scrim').classList.add('hidden'); }

CHIPS.forEach(([label, question]) => {
  const b = el('button', 'cursor-pointer rounded-full bg-tint px-2.5 py-1 text-xs font-medium text-brand transition hover:bg-brand hover:text-paper', label);
  b.type = 'button';
  b.onclick = () => { input.value = question; autosize(); input.focus(); };
  $('chips').append(b);
});

$('btnSend').onclick = () => send(input.value);
$('btnNew').onclick = newChat;
if($('btnClear')) $('btnClear').onclick = clearAll;
if($('histSearch')) $('histSearch').addEventListener('input', e => { histQuery = e.target.value; renderHist(); });
$('btnFaq').onclick = () => { closeMenu(); $('faq').showModal(); };
$('btnMenu').onclick = openMenu;
$('scrim').onclick = closeMenu;
input.addEventListener('input', autosize);
input.addEventListener('keydown', e => {
  if(e.key === 'Enter' && !e.shiftKey && !e.isComposing){ e.preventDefault(); send(input.value); }
});

/* Ubah lebar sidebar */
const SIDE_KEY = 'llm-surabaya-side-w', SIDE_MIN = 240, SIDE_MAX = 520, SIDE_DEF = 304;
const rz = $('resizer');
let curW = SIDE_DEF;
function setSide(px){
  px = Math.max(SIDE_MIN, Math.min(SIDE_MAX, Math.round(px)));
  document.documentElement.style.setProperty('--side-w', px + 'px');
  return px;
}
function saveSide(){ try { localStorage.setItem(SIDE_KEY, curW); } catch(e){} }
try { const s = parseInt(localStorage.getItem(SIDE_KEY)); if(s) curW = setSide(s); } catch(e){}

rz.addEventListener('pointerdown', e => {
  e.preventDefault();
  rz.setPointerCapture(e.pointerId);
  document.body.style.userSelect = 'none';
  document.body.style.cursor = 'col-resize';
  const move = ev => { curW = setSide(ev.clientX); };
  const up = () => {
    rz.removeEventListener('pointermove', move);
    rz.removeEventListener('pointerup', up);
    rz.removeEventListener('pointercancel', up);
    document.body.style.userSelect = '';
    document.body.style.cursor = '';
    saveSide();
  };
  rz.addEventListener('pointermove', move);
  rz.addEventListener('pointerup', up);
  rz.addEventListener('pointercancel', up);
});
rz.addEventListener('dblclick', () => { curW = setSide(SIDE_DEF); saveSide(); });

render();
(async () => {
  if(!LOGGED_IN) return;
  if(!(await refreshSessions())) showFormErr(MSG.offline);
  if(sessions.length) await openChat(sessions[0].id);
})();
</script>
</body>
</html>

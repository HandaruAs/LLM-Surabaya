<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ config('app.name', 'LLM Surabaya') }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;700&family=Source+Serif+4:wght@400;600&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-paper font-sans text-base leading-relaxed text-ink">
<div class="relative grid h-dvh grid-cols-1 md:grid-cols-[var(--side-w)_minmax(0,1fr)]">

  <aside id="side" class="fixed inset-y-0 left-0 z-20 flex w-[19rem] max-w-[88vw] -translate-x-full flex-col gap-4 overflow-y-auto border-r border-line bg-card px-4 py-5 transition-transform md:static md:w-auto md:max-w-none md:translate-x-0">
    <div class="flex items-center gap-3">
      <div class="grid size-10 place-items-center rounded-xl bg-brand text-paper"><i data-icon="landmark" class="block size-5"></i></div>
      <div>
        <div class="font-serif text-lg leading-tight font-semibold">LLM Surabaya</div>
        <div class="text-xs text-mute">Informasi keuangan daerah</div>
      </div>
    </div>

    <button id="btnNew" type="button" class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-brand px-3 py-2.5 font-medium text-brand hover:bg-tint">
      <i data-icon="newchat" class="block size-5"></i>Obrolan baru
    </button>

    @auth('jwt')
    <div class="flex min-h-[7rem] flex-1 flex-col gap-1">
      <div class="flex items-center justify-between px-1 text-xs text-mute">
        <span>Riwayat</span>
        <button id="btnClear" type="button" class="cursor-pointer hover:text-ink">Hapus semua</button>
      </div>
      <nav id="hist" class="flex min-h-0 flex-1 flex-col gap-0.5 overflow-y-auto"></nav>
    </div>
    @else
    {{-- Tamu: tidak ada riwayat tersimpan. --}}
    <div class="flex flex-1 flex-col">
      @if (auth('web')->user()?->isAdmin())
      <a href="{{ route('admin.dashboard') }}" class="mb-3 flex items-center justify-between rounded-xl border border-brand px-3.5 py-2.5 text-sm font-medium text-brand hover:bg-tint">
        Kembali ke area admin <span aria-hidden="true">→</span>
      </a>
      @endif
      <div class="rounded-xl border border-line p-3.5">
        <div class="text-sm font-medium">Riwayat tidak disimpan</div>
        <p class="mt-1 text-sm text-mute">Kamu belum masuk. Percakapan ini akan hilang saat halaman ditutup atau dimuat ulang.</p>
        <div class="mt-3 flex gap-2">
          <a href="{{ route('login') }}" class="flex-1 rounded-lg bg-brand px-3 py-2 text-center text-sm font-medium text-paper hover:opacity-90">Masuk</a>
          <a href="{{ route('register') }}" class="flex-1 rounded-lg border border-line px-3 py-2 text-center text-sm font-medium hover:bg-tint">Daftar</a>
        </div>
      </div>
    </div>
    @endauth

    <div class="shrink-0 border-t border-line pt-3">
      <button id="btnFaq" type="button" class="flex w-full cursor-pointer items-center justify-between rounded-lg px-2.5 py-2 text-sm font-medium hover:bg-tint">
        <span class="flex items-center gap-2.5"><i data-icon="help" class="block size-4 text-brand"></i>FAQ</span>
        <i data-icon="chevron" class="block size-4 text-mute"></i>
      </button>
      <div id="contact" class="mt-3 rounded-xl bg-tint p-3"></div>
      @auth('jwt')
      <div class="mt-3 flex items-center justify-between gap-2 px-1">
        <div class="min-w-0">
          <div class="truncate text-sm font-medium">{{ auth('jwt')->user()->name }}</div>
          <div class="truncate text-xs text-mute">{{ auth('jwt')->user()->email }}</div>
        </div>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit" class="cursor-pointer rounded-lg border border-line px-2.5 py-1.5 text-xs font-medium hover:bg-tint">Keluar</button>
        </form>
      </div>
      @endauth
    </div>
  </aside>
  <div id="scrim" class="fixed inset-0 z-10 hidden bg-black/40 md:hidden"></div>

  <div id="resizer" role="separator" aria-orientation="vertical" aria-label="Ubah lebar sidebar"
       class="absolute inset-y-0 z-20 hidden w-1.5 cursor-col-resize touch-none transition-colors hover:bg-brand/40 active:bg-brand/60 md:left-[calc(var(--side-w)-3px)] md:block"></div>

  <section class="flex min-h-0 min-w-0 flex-col">
    <header class="relative flex items-center gap-2 border-b border-line bg-card px-3 py-3 md:px-6">
      <button id="btnMenu" type="button" aria-label="Buka menu" class="grid size-9 cursor-pointer place-items-center rounded-lg hover:bg-tint md:hidden"><i data-icon="menu" class="block size-5"></i></button>
      <h1 id="title" class="truncate font-serif text-lg font-semibold"></h1>
      <span class="absolute bottom-[-1px] left-0 h-[3px] w-16 bg-gold"></span>
    </header>

    <div id="stream" class="flex-1 overflow-y-auto px-4 py-8 md:px-8">
      <div id="col" class="mx-auto flex max-w-4xl flex-col gap-10"></div>
    </div>

    <div class="px-4 pt-2 pb-4 md:px-8">
      <div class="mx-auto max-w-4xl">
        <p id="formErr" role="alert" class="mb-2 hidden rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 dark:bg-red-950 dark:text-red-300"></p>
        <div class="flex items-end gap-2 rounded-2xl border border-line bg-card py-2 pr-2 pl-4 shadow-sm focus-within:border-brand">
          <textarea id="input" rows="1" placeholder="Tulis pertanyaan Anda tentang pajak, anggaran, atau retribusi" aria-label="Pertanyaan"
                    class="max-h-40 flex-1 resize-none bg-transparent py-1.5 leading-normal outline-none placeholder:text-mute"></textarea>
          <button id="btnSend" type="button" aria-label="Kirim" class="grid size-10 shrink-0 cursor-pointer place-items-center rounded-xl bg-brand text-paper hover:opacity-90 disabled:cursor-wait disabled:opacity-50"><i data-icon="send" class="block size-5"></i></button>
        </div>
        <p class="mt-2 text-center text-xs text-mute">Informasi bersifat panduan umum. Untuk keputusan penting, pastikan ke instansi resmi.<br>Jangan menuliskan data pribadi seperti NIK atau nomor rekening. @auth Riwayat tersimpan di akunmu. @else Riwayat tidak disimpan karena kamu belum masuk. @endauth</p>
      </div>
    </div>
  </section>
</div>

<div id="modal" class="fixed inset-0 z-30 hidden place-items-center bg-black/50 p-4" role="dialog" aria-modal="true">
  <div class="flex max-h-[85dvh] w-full max-w-xl flex-col overflow-hidden rounded-2xl bg-card shadow-xl">
    <div class="flex items-center justify-between border-b border-line px-5 py-3">
      <h2 class="font-serif text-lg font-semibold">FAQ</h2>
      <button id="mClose" type="button" aria-label="Tutup" class="grid size-8 cursor-pointer place-items-center rounded-lg hover:bg-tint"><i data-icon="x" class="block size-5"></i></button>
    </div>
    <div id="mBody" class="overflow-y-auto px-5 py-3"></div>
  </div>
</div>

<script nonce="{{ Vite::cspNonce() }}">
/* ---------- Koneksi ke API backend (laravel_app/API.md) ---------- */
const API = @json(url('/api'));
const FB_KEY = 'llm-surabaya-feedback';
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

// Login memakai session (cookie). Tamu boleh bertanya, tapi riwayatnya tidak disimpan:
// percakapan tamu hanya ada di memori halaman ini dan dikirim ulang sebagai "history".
const LOGGED_IN = @json(auth('jwt')->check());
const USER_NAME = @json(auth('jwt')->user()?->name);

// Panggil API. Selalu mengembalikan { ok, status, data } — status 0 berarti server tidak bisa dihubungi.
async function api(path, method = 'GET', body){
  try {
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

const CONTACT = {
  phones: [['(031) 5321444', '+62315321444'], ['(031) 99277339', '+623199277339']],
  emails: ['dinkominfo@surabaya.go.id', 'mediacenter@surabaya.go.id'],
  address: 'Jl. Jimerto No. 25-27, Ketabang, Kec. Genteng, Kota Surabaya, Jawa Timur 60272'
};

const P = {
  landmark: '<line x1="3" x2="21" y1="22" y2="22"/><line x1="6" x2="6" y1="18" y2="11"/><line x1="10" x2="10" y1="18" y2="11"/><line x1="14" x2="14" y1="18" y2="11"/><line x1="18" x2="18" y1="18" y2="11"/><polygon points="12 2 20 7 4 7"/>',
  send: '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
  newchat: '<path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"/>',
  trash: '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
  help: '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>',
  menu: '<line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/>',
  x: '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
  copy: '<rect width="14" height="14" x="8" y="8" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
  up: '<path d="M7 10v12"/><path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2a3.13 3.13 0 0 1 3 3.88Z"/>',
  down: '<path d="M17 14V2"/><path d="M9 18.12 10 14H4.17a2 2 0 0 1-1.92-2.56l2.33-8A2 2 0 0 1 6.5 2H20a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2.76a2 2 0 0 0-1.79 1.11L12 22a3.13 3.13 0 0 1-3-3.88Z"/>',
  file: '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M16 13H8"/><path d="M16 17H8"/>',
  phone: '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
  mail: '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
  pin: '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
  chevron: '<path d="m9 18 6-6-6-6"/>',
  follow: '<polyline points="15 10 20 15 15 20"/><path d="M4 4v7a4 4 0 0 0 4 4h12"/>',
  home: '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
  pie: '<path d="M21 12c.552 0 1.005-.449.95-.998a10 10 0 0 0-8.953-8.951c-.55-.055-.998.398-.998.95v8a1 1 0 0 0 1 1z"/><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/>',
  receipt: '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 17.5v-11"/>',
  wallet: '<path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>'
};

const TOPICS = [];

const FOLLOW = ['Jelaskan dengan bahasa yang lebih sederhana', 'Apa dasar hukumnya?', 'Berikan contoh perhitungannya'];

const FAQ = [
  ['Apa perbedaan pajak daerah dan retribusi daerah?', 'Pajak daerah adalah pungutan wajib kepada daerah oleh orang pribadi atau badan tanpa imbalan langsung, dan hasilnya dipakai untuk kepentingan daerah. Retribusi dibayar atas jasa atau perizinan tertentu yang disediakan pemerintah daerah, sehingga pembayarnya menerima imbalan langsung.'],
  ['Apa itu APBD dan mengapa penting bagi warga?', 'APBD adalah rencana keuangan tahunan pemerintah daerah yang ditetapkan bersama DPRD. Isinya mencakup pendapatan, belanja, dan pembiayaan daerah. Dari sinilah layanan publik dan pembangunan dibiayai.'],
  ['Apa itu PBB-P2 dan siapa yang wajib membayarnya?', 'PBB-P2 adalah Pajak Bumi dan Bangunan Perdesaan dan Perkotaan yang dikelola pemerintah daerah. Yang wajib membayar adalah orang pribadi atau badan yang memiliki, menguasai, atau memperoleh manfaat atas bumi dan/atau bangunan.'],
  ['Apa itu BPHTB dan kapan dibayar?', 'BPHTB adalah Bea Perolehan Hak atas Tanah dan Bangunan, yaitu pajak atas perolehan hak, misalnya karena jual beli, hibah, atau waris. Umumnya dibayar oleh pihak yang memperoleh hak. Waktu dan tata cara pembayarannya mengikuti ketentuan daerah, sebaiknya konfirmasi ke petugas.'],
  ['Bagaimana jika terlambat membayar pajak daerah?', 'Keterlambatan umumnya dikenai sanksi administratif berupa denda. Besaran dan ketentuannya diatur dalam peraturan daerah, jadi periksa ketentuan yang berlaku atau tanyakan kepada petugas.']
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

function el(tag, cls, text){
  const n = document.createElement(tag);
  if(cls) n.className = cls;
  if(text !== undefined) n.textContent = text;
  return n;
}
function svg(n){ return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="size-full">' + (P[n] || '') + '</svg>'; }
function ic(n, cls){ const s = el('span', cls || 'block size-4 shrink-0'); s.innerHTML = svg(n); return s; }
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

function renderContact(){
  const box = $('contact');
  box.append(el('div', 'text-sm font-medium', 'Hubungi kami'));
  const ul = el('ul', 'mt-3 space-y-2.5 text-sm text-mute');
  const item = (icon, inner) => {
    const li = el('li', 'flex items-start gap-2.5');
    li.append(ic(icon, 'mt-0.5 block size-3.5 shrink-0 text-brand'), inner);
    ul.append(li);
  };
  const links = (arr, hrefOf, textOf) => {
    const d = el('div', 'flex min-w-0 flex-col gap-1');
    arr.forEach(x => { const a = el('a', 'break-all hover:text-ink hover:underline', textOf(x)); a.href = hrefOf(x); d.append(a); });
    return d;
  };
  item('phone', links(CONTACT.phones, x => 'tel:' + x[1], x => x[0]));
  item('mail', links(CONTACT.emails, x => 'mailto:' + x, x => x));
  const addr = el('address', 'not-italic leading-relaxed', CONTACT.address);
  item('pin', addr);
  box.append(ul);
}

async function refreshSessions(){
  if(!LOGGED_IN) return true; // tamu tidak punya riwayat tersimpan
  const r = await api('/sessions');
  if(r.ok) sessions = r.data.data || [];
  renderHist();
  return r.ok;
}

// Sesi baru dari server: pindahkan draf ke sesi itu dan taruh di paling atas riwayat.
function adopt(id, title){
  if(activeId === null){ msgs[id] = draft; draft = []; activeId = id; }
  sessions = [{ id, title: title || 'Obrolan baru', updated_at: new Date().toISOString() }, ...sessions.filter(s => s.id !== id)];
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
  }
  render();
}

async function delChat(id){
  if(busy || !confirm('Hapus percakapan ini?')) return;
  const r = await api('/sessions/' + id, 'DELETE');
  if(!r.ok && r.status !== 404){ alert(r.status === 0 ? MSG.offline : MSG.generic); return; }
  sessions = sessions.filter(s => s.id !== id);
  delete msgs[id]; delete errors[id];
  if(activeId === id) activeId = null;
  render();
}

async function clearAll(){
  if(busy || !sessions.length || !confirm('Hapus semua riwayat?')) return;
  const r = await api('/sessions', 'DELETE');
  if(!r.ok){ alert(r.status === 0 ? MSG.offline : MSG.generic); return; }
  sessions = []; activeId = null; draft = [];
  Object.keys(msgs).forEach(k => delete msgs[k]);
  Object.keys(errors).forEach(k => delete errors[k]);
  render();
}

function renderHist(){
  if(!hist) return; // tamu: panel riwayat tidak ada
  hist.innerHTML = '';
  if(!sessions.length){ hist.append(el('div', 'px-2 py-3 text-sm text-mute', 'Belum ada riwayat.')); return; }
  sessions.forEach(c => {
    const on = c.id === activeId;
    const row = el('div', 'group flex items-center rounded-lg border-l-[3px] ' + (on ? 'border-brand bg-tint' : 'border-transparent hover:bg-tint'));
    const open = el('button', 'min-w-0 flex-1 cursor-pointer bg-transparent px-2.5 py-2 text-left');
    open.type = 'button';
    open.append(el('div', 'truncate text-sm font-medium', c.title), el('div', 'text-xs text-mute', dayLabel(c.updated_at)));
    open.onclick = () => openChat(c.id);
    const del = el('button', 'mr-1 cursor-pointer rounded-md p-1.5 text-mute hover:text-red-500 focus-visible:opacity-100 md:opacity-0 md:group-hover:opacity-100');
    del.type = 'button';
    del.setAttribute('aria-label', 'Hapus percakapan');
    del.append(ic('trash'));
    del.onclick = () => delChat(c.id);
    row.append(open, del);
    hist.append(row);
  });
}

function sourceLabel(s){
  if(typeof s === 'string') return s;
  if(s && typeof s === 'object'){
    const name = s.title || s.source || s.filename || s.file || s.url || '';
    const page = s.page !== undefined ? ' (hal. ' + s.page + ')' : '';
    return name ? name + page : JSON.stringify(s);
  }
  return String(s);
}

function iconBtn(icon, label, on, onclick){
  const b = el('button', 'flex cursor-pointer items-center gap-1.5 rounded-md px-2 py-1 text-xs hover:bg-tint ' + (on ? 'text-brand' : 'text-mute'));
  b.type = 'button';
  b.title = label;
  b.setAttribute('aria-label', label);
  b.append(ic(icon, 'block size-4 shrink-0'));
  b.onclick = () => onclick(b);
  return b;
}

function renderEntry(q, a, last){
  const e = el('article', 'grid gap-x-8 gap-y-3 border-b border-line pb-10 last:border-0 last:pb-0 lg:grid-cols-[minmax(0,1fr)_13rem]');
  const head = el('div', 'flex items-baseline justify-between gap-4 border-l-[3px] border-brand pl-4 lg:col-span-2');
  head.append(el('h2', 'font-serif text-xl leading-snug font-semibold break-words', q.content));
  if(q.t) head.append(el('time', 'shrink-0 text-xs text-mute', hm(q.t)));
  e.append(head);

  const body = el('div', 'min-w-0 pl-4');
  if(!a){
    if(busy && last){
      const p = el('div', 'flex flex-col gap-2 text-sm text-mute');
      const bar = el('div', 'h-0.5 w-44 overflow-hidden rounded bg-line');
      bar.append(el('div', 'sweep h-full w-1/3 bg-brand'));
      p.append(el('span', '', 'Menyusun jawaban…'), bar);
      body.append(p);
    } else if(last && activeId !== null){
      // Tersimpan di server tapi belum dijawab: tawarkan kirim ulang (POST /api/sessions/{id}/retry).
      const box = el('div', 'flex flex-wrap items-center gap-3 rounded-lg bg-red-50 px-3 py-2 text-red-800 dark:bg-red-950 dark:text-red-300');
      box.append(el('span', 'text-sm', errors[activeId] || MSG.unanswered));
      const again = el('button', 'cursor-pointer rounded-md border border-current px-2.5 py-1 text-sm font-medium hover:opacity-80', 'Kirim ulang');
      again.type = 'button';
      again.onclick = retry;
      box.append(again);
      body.append(box);
    } else {
      body.append(el('div', 'text-sm text-mute', 'Tidak terjawab.'));
    }
    e.append(body);
    return e;
  }
  body.append(el('div', 'max-w-[68ch] font-serif text-[1.0625rem] leading-[1.75] break-words whitespace-pre-wrap' + (a.error ? ' rounded-lg bg-red-50 px-3 py-2 font-sans text-base text-red-800 dark:bg-red-950 dark:text-red-300' : ''), a.content));

  if(!a.error){
    const acts = el('div', 'mt-3 -ml-2 flex items-center gap-1');
    acts.append(iconBtn('copy', 'Salin jawaban', false, b => {
      navigator.clipboard.writeText(a.content).then(() => { b.title = 'Tersalin'; b.classList.add('text-brand'); setTimeout(() => { b.title = 'Salin jawaban'; b.classList.remove('text-brand'); }, 1500); }).catch(() => {});
    }));
    if(a.id){
      const fb = fbGet(a.id);
      acts.append(iconBtn('up', 'Membantu', fb === 'up', () => { fbSet(a.id, fb === 'up' ? '' : 'up'); render(); }));
      acts.append(iconBtn('down', 'Kurang membantu', fb === 'down', () => { fbSet(a.id, fb === 'down' ? '' : 'down'); render(); }));
    }
    body.append(acts);
  }

  const followups = a.followups && a.followups.length ? a.followups : FOLLOW;
  if(last && !a.error && !busy){
    const f = el('div', 'mt-4 border-t border-line pt-3');
    f.append(el('div', 'mb-1 text-sm text-mute', 'Lanjutkan dengan'));
    followups.forEach(txt => {
      const b = el('button', 'flex w-full cursor-pointer items-center gap-2 py-1.5 text-left text-brand hover:underline');
      b.type = 'button';
      b.append(ic('follow'), el('span', '', txt));
      b.onclick = () => send(txt);
      f.append(b);
    });
    body.append(f);
  }
  e.append(body);

  const list = Array.isArray(a.sources) ? a.sources : (a.sources ? [a.sources] : []);
  if(!a.error && list.length){
    const side = el('aside', 'pl-4 text-sm text-mute lg:border-l lg:border-line');
    side.append(el('div', 'mb-1.5 font-medium text-ink', 'Rujukan'));
    const ul = el('ul', 'flex flex-col gap-1.5');
    list.forEach(s => {
      const li = el('li', 'flex items-start gap-2');
      li.append(ic('file', 'mt-0.5 block size-3.5 shrink-0 text-brand'), el('span', 'min-w-0 break-words', sourceLabel(s)));
      ul.append(li);
    });
    side.append(ul);
    e.append(side);
  }
  return e;
}

function renderWelcome(){
  const w = el('div', 'mx-auto mt-[2vh] w-full max-w-3xl');
  const date = new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
  w.append(el('div', 'text-sm text-mute', date));
  w.append(el('h2', 'mt-2 font-serif text-3xl leading-tight font-semibold md:text-4xl', greet() + '. Apa yang ingin Anda ketahui?'));
  w.append(el('div', 'my-5 h-[3px] w-14 bg-gold'));
  w.append(el('p', 'mb-8 max-w-[60ch] text-mute', 'Tanyakan seputar pajak daerah, anggaran, dan retribusi Kota Surabaya. Jawaban dilengkapi rujukan agar mudah ditelusuri.'));
  const list = el('div', 'divide-y divide-line border-y border-line');
  TOPICS.forEach(tp => {
    const b = el('button', 'group grid w-full cursor-pointer grid-cols-[2.5rem_minmax(0,1fr)_auto] items-center gap-4 px-2 py-4 text-left hover:bg-tint');
    b.type = 'button';
    const i = el('div', 'grid size-10 place-items-center rounded-full bg-tint text-brand group-hover:bg-card');
    i.append(ic(tp.icon, 'block size-5'));
    const txt = el('div', 'min-w-0');
    txt.append(el('div', 'font-serif text-lg font-semibold', tp.title), el('div', 'text-sm text-mute', tp.q));
    b.append(i, txt, ic('chevron', 'block size-5 text-mute transition-transform group-hover:translate-x-1 group-hover:text-brand'));
    b.onclick = () => send(tp.q);
    list.append(b);
  });
  w.append(list);
  return w;
}

function render(){
  renderHist();
  const c = active();
  const ms = current();
  $('title').textContent = c ? c.title : (ms.length ? ms[0].content.slice(0, 40) : 'Obrolan baru');
  col.innerHTML = '';
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
  scrollDown();
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

function autosize(){
  input.style.height = 'auto';
  input.style.height = Math.min(input.scrollHeight, 160) + 'px';
}
function openMenu(){ $('side').classList.remove('-translate-x-full'); $('scrim').classList.remove('hidden'); }
function closeMenu(){ $('side').classList.add('-translate-x-full'); $('scrim').classList.add('hidden'); }

function openFaq(){
  const b = $('mBody');
  b.innerHTML = '';
  FAQ.forEach(([q, a]) => {
    const d = el('details', 'group border-b border-line py-2 last:border-0');
    const s = el('summary', 'flex cursor-pointer items-center justify-between gap-3 py-1.5 font-serif text-lg font-semibold');
    s.append(el('span', '', q), ic('chevron', 'block size-4 shrink-0 text-mute transition-transform group-open:rotate-90'));
    d.append(s, el('p', 'pb-2 text-mute', a));
    b.append(d);
  });
  $('modal').classList.remove('hidden');
  $('modal').classList.add('grid');
  closeMenu();
}
function hideModal(){ $('modal').classList.add('hidden'); $('modal').classList.remove('grid'); }

document.querySelectorAll('[data-icon]').forEach(n => n.innerHTML = svg(n.dataset.icon));
$('btnSend').onclick = () => send(input.value);
$('btnNew').onclick = newChat;
if($('btnClear')) $('btnClear').onclick = clearAll;
$('btnFaq').onclick = openFaq;
$('btnMenu').onclick = openMenu;
$('scrim').onclick = closeMenu;
$('mClose').onclick = hideModal;
$('modal').addEventListener('click', e => { if(e.target === $('modal')) hideModal(); });
document.addEventListener('keydown', e => { if(e.key === 'Escape') hideModal(); });
input.addEventListener('input', autosize);
input.addEventListener('keydown', e => {
  if(e.key === 'Enter' && !e.shiftKey && !e.isComposing){ e.preventDefault(); send(input.value); }
});

/* Resize sidebar */
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

renderContact();
render();
(async () => {
  if(!LOGGED_IN) return;
  if(!(await refreshSessions())) showFormErr(MSG.offline);
  if(sessions.length) await openChat(sessions[0].id);
})();
</script>
</body>
</html>
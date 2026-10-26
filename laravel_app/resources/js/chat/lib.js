// Fungsi bantu halaman chat (dipindah apa adanya dari script lama di chat.blade.php).

export const MSG = {
  offline: 'Tidak bisa terhubung ke server. Periksa koneksi lalu coba lagi.',
  aiDown: 'Layanan AI sedang tidak tersedia. Pertanyaan Anda sudah tersimpan, silakan kirim ulang.',
  unanswered: 'Pertanyaan ini belum terjawab.',
  generic: 'Terjadi kesalahan. Silakan coba lagi.',
};

// Kartu contoh pertanyaan di layar sambutan. Kosongkan array ini untuk menyembunyikannya.
// icon = nama ikon di App\Support\Icons.
export const TOPICS = [
  { icon: 'pie', title: 'APBD Surabaya', q: 'Berapa total pendapatan dalam APBD Kota Surabaya tahun 2025?' },
  { icon: 'receipt', title: 'Pajak daerah', q: 'Apa saja jenis pajak daerah di Kota Surabaya?' },
  { icon: 'wallet', title: 'Retribusi', q: 'Apa perbedaan pajak daerah dan retribusi daerah?' },
  { icon: 'home', title: 'PBB-P2', q: 'Apa itu PBB-P2 dan siapa yang wajib membayarnya?' },
];

// Chip di kolom tulis: mengisi contoh pertanyaan.
export const CHIPS = [
  ['APBD 2025', 'Berapa total pendapatan dalam APBD Kota Surabaya tahun 2025?'],
  ['Pajak daerah', 'Apa saja jenis pajak daerah di Kota Surabaya?'],
];

// Panggil API. Selalu mengembalikan { ok, status, data } — status 0 berarti server tidak bisa dihubungi.
export function makeApi(base, csrf) {
  return async function api(path, method = 'GET', body) {
    try {
      const res = await fetch(base + path, {
        method,
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: body === undefined ? undefined : JSON.stringify(body),
      });
      const data = res.status === 204 ? {} : await res.json().catch(() => ({}));
      return { ok: res.ok, status: res.status, data };
    } catch (e) {
      return { ok: false, status: 0, data: {} };
    }
  };
}

export function fromApi(m) {
  return { id: m.id, role: m.role, content: m.content, sources: m.sources || [], t: Date.parse(m.created_at) || Date.now() };
}

// Judul di header: maksimal 40 karakter. Kalau batasnya jatuh di tengah kata,
// mundur ke kata sebelumnya, lalu tambahkan titik-titik.
export function shortTitle(text, max = 40) {
  text = (text || '').replace(/\s+/g, ' ').trim();
  if (text.length <= max) return text;
  let cut = text.slice(0, max);
  if (text[max] !== ' ') {
    const i = cut.lastIndexOf(' ');
    if (i > 0) cut = cut.slice(0, i);
  }
  return cut.replace(/[\s,.;:!?-]+$/, '') + '…';
}

export function hm(ts) {
  return new Date(ts).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}

export function dayLabel(iso) {
  const ts = Date.parse(iso);
  if (!ts) return '';
  const d = new Date(ts), now = new Date();
  const diff = Math.round((new Date(now.getFullYear(), now.getMonth(), now.getDate()) - new Date(d.getFullYear(), d.getMonth(), d.getDate())) / 86400000);
  if (diff === 0) return 'Hari ini';
  if (diff === 1) return 'Kemarin';
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
}

export function greet(name) {
  const h = new Date().getHours();
  const timeGreet = h < 11 ? 'Selamat pagi' : h < 15 ? 'Selamat siang' : h < 18 ? 'Selamat sore' : 'Selamat malam';
  return name ? `${timeGreet} ${name}` : timeGreet;
}

// Nama sumber untuk ditampilkan: buang awalan internal dokumen admin ("admin:5:").
export function sourceName(s) {
  const name = typeof s === 'string' ? s : (s && (s.title || s.source || s.filename || s.file || s.url)) || String(s);
  return name.replace(/^admin:\d+:/, '');
}

export function sourceType(name) {
  const ext = (name.split('.').pop() || '').toLowerCase();
  return ext === 'xlsx' ? 'Excel' : ext ? ext.toUpperCase() : 'Dokumen';
}

export function sourcesOf(a) {
  return (Array.isArray(a.sources) ? a.sources : a.sources ? [a.sources] : []).map(sourceName);
}

// localStorage yang aman (mode privat / storage diblokir tidak membuat halaman error).
export function loadJson(key, fallback) {
  try { return JSON.parse(localStorage.getItem(key)) || fallback; } catch (e) { return fallback; }
}
export function saveJson(key, value) {
  try { localStorage.setItem(key, JSON.stringify(value)); } catch (e) {}
}

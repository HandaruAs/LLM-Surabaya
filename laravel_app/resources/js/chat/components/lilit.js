// Konfigurasi waktu untuk animasi intro hewan vertikal.
//
// Koordinat: titik (0,0) = pusat panggung 320×320, x ke kanan, y ke bawah, z ke arah penonton.
// Tugu setinggi 280px: puncak y ≈ -140, kaki y ≈ +140.

const TILT = 0.2; // kemiringan kamera: sisi depan orbit tampak sedikit lebih rendah (dilihat agak dari atas)
const STRIPS = 56; // jumlah irisan per hewan; makin banyak makin halus lengkungannya
const DEG = Math.PI / 180;

const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
const lerp = (a, b, t) => a + (b - a) * t;
export const smoothstep = (a, b, v) => {
  const t = clamp((v - a) / (b - a), 0, 1);
  return t * t * (3 - 2 * t);
};
// ease in-out lembut (tidak terlalu lama tertahan di awal, melambat saat mengunci di akhir)
export const easeInOut = (t) => 0.5 - 0.5 * Math.cos(Math.PI * clamp(t, 0, 1));

// Interpolasi linear pada daftar titik [[x, y], ...]
function table(points, x) {
  if (x <= points[0][0]) return points[0][1];
  for (let i = 1; i < points.length; i += 1) {
    if (x <= points[i][0]) {
      const [x0, y0] = points[i - 1];
      const [x1, y1] = points[i];
      return lerp(y0, y1, (x - x0) / (x1 - x0));
    }
  }
  return points[points.length - 1][1];
}

// Jarak orbit dari sumbu tugu di setiap ketinggian: tugu meruncing ke atas dan melebar di kaki,
// jadi orbit ikut sempit di atas dan lebar di bawah supaya badan hewan tidak "menembus" tugu di sisi.
const RADIUS_BY_Y = [
  [-140, 36],
  [-90, 38],
  [-40, 44],
  [20, 50],
  [70, 64],
  [100, 82],
  [130, 98],
];

/**
 * Siapkan satu hewan: buat tabel jalur spiral + panjang lintasan.
 * cfg:
 *   img       HTMLImageElement yang sudah termuat (hewan lurus, kepala di kiri)
 *   length    panjang badan di layar (px)
 *   spine     [[f, y/tinggi]...] posisi garis tengah badan sepanjang gambar (f: 0 = kepala, 1 = ekor)
 *   dir       +1: di depan tugu bergerak ke kiri; -1: di depan tugu bergerak ke kanan
 *   angleEnd  sudut kepala di akhir (90° = tepat di depan tugu, 270° = tepat di belakang)
 *   yStart, yEnd  ketinggian kepala di awal & akhir
 *   turns     jumlah putaran kepala dari awal ke akhir
 *   radius    pengali jarak orbit (1 = default)
 *   motion    'swim' (hiu meliuk) atau 'crawl' (buaya merangkak)
 */
export function makeAnimal(cfg) {
  const P = cfg.turns * 360; // derajat yang ditempuh kepala
  const pMin = -900; // jalur diperpanjang ke belakang agar ekor punya tempat di awal animasi
  const step = 0.5;
  const ps = [];
  const cum = [];
  let prev = null;
  let s = 0;
  for (let p = pMin; p <= P + 0.001; p += step) {
    const q = pos3(cfg, P, p);
    if (prev) s += Math.hypot(q.x - prev.x, q.y - prev.y, q.z - prev.z);
    ps.push(p);
    cum.push(s);
    prev = q;
  }
  const sAtStart = cum[Math.round((0 - pMin) / step)];
  return { cfg, P, ps, cum, sAtStart, sAtEnd: s };
}

function pos3(cfg, P, p) {
  const a = (cfg.angleEnd - cfg.dir * (P - p)) * DEG;
  const y = cfg.yStart + (cfg.yEnd - cfg.yStart) * (p / P);
  const r = table(RADIUS_BY_Y, y) * (cfg.radius || 1);
  return { x: r * Math.cos(a), y, z: r * Math.sin(a) };
}

// Ubah panjang lintasan s -> parameter p (pencarian biner pada tabel)
function pAtS(an, s) {
  const { cum, ps } = an;
  if (s <= cum[0]) return ps[0];
  if (s >= cum[cum.length - 1]) return ps[ps.length - 1];
  let lo = 0;
  let hi = cum.length - 1;
  while (hi - lo > 1) {
    const mid = (lo + hi) >> 1;
    if (cum[mid] < s) lo = mid;
    else hi = mid;
  }
  const t = (s - cum[lo]) / (cum[hi] - cum[lo] || 1);
  return lerp(ps[lo], ps[hi], t);
}

/**
 * Hitung semua irisan satu hewan pada progres t (0..1) dan waktu `time` (detik, untuk goyangan).
 * live: 1 = goyangan penuh, 0 = diam (dipakai saat mengunci di pose akhir).
 */
function strips(an, t, time, live) {
  const { cfg, P } = an;
  const img = cfg.img;
  const W = img.naturalWidth;
  const H = img.naturalHeight;
  const scale = cfg.length / W; // piksel gambar -> piksel layar

  // Buaya bergerak "melangkah": maju-tertahan-maju, bukan meluncur rata.
  let tt = t;
  if (cfg.motion === 'crawl') {
    tt = clamp(t + 0.014 * Math.sin(2 * Math.PI * 1.3 * time) * live * smoothstep(0, 0.12, t), 0, 1);
  }
  const sHead = lerp(an.sAtStart, an.sAtEnd, tt);

  // Posisi titik di badan pada fraksi f (0 = kepala, 1 = ekor), sudah termasuk goyangan.
  const point = (f) => {
    const q = pos3(cfg, P, pAtS(an, sHead - f * cfg.length));
    let dy = 0;
    if (cfg.motion === 'swim') {
      // Hiu meliuk: gelombang merambat dari kepala ke ekor, makin besar di ekor.
      const amp = (4 + 24 * f * f) * live;
      dy = amp * Math.sin(2 * Math.PI * (1.4 * f - 1.9 * time));
    } else {
      // Buaya merangkak: badan berayun pelan + terangkat tiap langkah.
      const amp = (1.5 + 4 * f) * live;
      dy = amp * Math.sin(2 * Math.PI * (0.9 * f - 1.3 * time));
      dy -= 3 * live * Math.abs(Math.sin(Math.PI * 2.6 * time)) * (1 - f);
    }
    return { x: q.x, y: q.y + dy, z: q.z };
  };

  const out = [];
  let a = point(0);
  for (let i = 0; i < STRIPS; i += 1) {
    const f0 = i / STRIPS;
    const f1 = (i + 1) / STRIPS;
    const b = point(f1);
    const zMid = (a.z + b.z) / 2;
    const k = 1 + 0.12 * (zMid / 100); // lebih besar di depan, lebih kecil di belakang
    const ax = a.x;
    const ay = a.y + TILT * a.z;
    const bx = b.x;
    const by = b.y + TILT * b.z;
    const sx = f0 * W;
    const sw = (f1 - f0) * W;
    out.push({
      z: zMid,
      sx,
      sw,
      // Sumbu-x irisan mengikuti arah badan (kepala -> ekor). Saat arah ini menunjuk ke kiri
      // (hewan lewat di belakang tugu), gambar otomatis tercermin, jadi tetap tegak, tidak terbalik.
      ex: (bx - ax) / sw,
      ey: (by - ay) / sw,
      // Sumbu-y irisan selalu tegak (punggung di atas, perut di bawah).
      d: scale * k,
      ox: ax,
      oy: ay,
      spine: table(cfg.spine, (f0 + f1) / 2) * H,
      H,
      W,
      img,
    });
    a = b;
  }
  return out;
}

/**
 * Gambar satu frame ke dua canvas: `back` (di belakang tugu) dan `front` (di depan tugu).
 * size: ukuran canvas dalam px CSS; titik pusat panggung ada di tengah canvas.
 */
export function drawFrame({ back, front, animals, t, time, live, alpha, dpr, size }) {
  const half = size / 2;
  const lists = { back: [], front: [] };
  for (const an of animals) {
    for (const s of strips(an, t, time, live)) (s.z >= 0 ? lists.front : lists.back).push(s);
  }
  for (const [ctx, list] of [[back, lists.back], [front, lists.front]]) {
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
    // Transparansi dipasang di canvas, bukan per irisan: irisan saling tumpang, jadi kalau
    // tiap irisan transparan, bagian tumpangnya tampak bergaris.
    ctx.globalAlpha = 1;
    ctx.canvas.style.opacity = String(alpha);
    ctx.imageSmoothingQuality = 'high';
    list.sort((p, q) => p.z - q.z); // yang lebih jauh digambar dulu
    for (const s of list) {
      // Irisan digambar sedikit lebih lebar dari jatahnya supaya tidak ada celah di lengkungan.
      const sw = Math.min(s.sw * 1.7, s.W - s.sx);
      ctx.setTransform(dpr * s.ex, dpr * s.ey, 0, dpr * s.d, dpr * (s.ox + half), dpr * (s.oy + half));
      ctx.drawImage(s.img, s.sx, 0, sw, s.H, 0, -s.spine, sw, s.H);
    }
  }
}

// ---------------------------------------------------------------------------
// Pengaturan hewan. Ubah angka di sini untuk menyetel gerakan.
// ---------------------------------------------------------------------------
export const SIZE = 400; // ukuran canvas (px); lebih besar dari panggung 320 supaya ekor tidak terpotong
export const MOVE_DELAY = 0.9; // detik: hewan mulai muncul setelah tugu selesai tumbuh
export const MOVE_DURATION = 2.7; // detik: lama gerak spiral sampai mengunci di pose akhir

// Suro (hiu): naik dari kaki tugu ke atas sambil meliuk.
export const SHARK = {
  src: '/images/suro-lurus.webp',
  length: 215,
  spine: [[0, 0.56], [1, 0.56]],
  dir: -1,
  angleEnd: 112, // berakhir di depan tugu (sedikit ke kiri), kepala menghadap kanan
  yStart: 92,
  yEnd: -62,
  turns: 1.5,
  radius: 1,
  motion: 'swim',
};

// Boyo (buaya): turun dari puncak ke kaki tugu sambil merangkak.
export const CROC = {
  src: '/images/boyo-lurus.webp',
  length: 230,
  spine: [[0, 0.49], [0.2, 0.42], [0.45, 0.43], [0.6, 0.5], [0.8, 0.55], [1, 0.61]],
  dir: 1,
  angleEnd: 75, // berakhir di depan kaki tugu, kepala menghadap kiri (seperti logo)
  yStart: -85,
  yEnd: 98,
  turns: 1.35,
  radius: 1.05,
  motion: 'crawl',
};

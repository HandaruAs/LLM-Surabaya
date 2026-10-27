import { useEffect, useState } from 'react';
import { AnimatePresence, animate, motion, useMotionValue, useReducedMotion, useTransform } from 'motion/react';

// Animasi pembuka setelah login/daftar: Suro (hiu) & Boyo (buaya) "masuk" ke pose logo.
// Urutan: lingkaran latar membesar -> tugu tumbuh dari bawah -> hiu melompat keluar dari air di
// bawah lingkaran, melengkung lewat di belakang tugu, lalu mendarat ke posenya -> buaya merayap
// turun dari atas sambil berayun lalu berhenti di kaki tugu -> keduanya bergerak kecil
// terus-menerus (hiu berayun, buaya bernapas) -> teks sambutan -> memudar.
// Diputar sekali saja. Klik / Esc untuk melewati.
//
// Gambar (folder public/images), latar transparan, dalam pose seperti di logo:
const tugu = '/images/tugu.webp';
const suro = '/images/suro.webp'; // hiu
const boyo = '/images/boyo.webp'; // buaya
const logo = '/images/suro-boyo-tugu.webp'; // hanya dipakai saat ALIGN_DEBUG = true

// ---------------------------------------------------------------------------
// Posisi akhir (pose logo). x/y = jarak titik tengah gambar dari titik tengah tugu (px),
// width = lebar gambar (px). Angka ini diukur dari logo dengan tugu setinggi 280px.
// Kalau gambar terpisahmu dipotong berbeda, nyalakan ALIGN_DEBUG: logo asli tampil samar di
// belakang, lalu geser angka di bawah sampai hewan menumpuk pas di atas bayangannya.
// ---------------------------------------------------------------------------
const ALIGN_DEBUG = false;
const SURO_POSE = { x: -15, y: -60, width: 218 };
const BOYO_POSE = { x: 75, y: 36, width: 152 };
const LOGO_GHOST = { height: 282, x: 14, y: 0 };

// Waktu (detik)
const SHARK_START = 0.85;
const SHARK_DURATION = 1.25;
const SHARK_LAND = SHARK_START + SHARK_DURATION;
const CROC_START = 1.35;
const CROC_DURATION = 1.6;
const CROC_LAND = CROC_START + CROC_DURATION;
const TEXT_AT = 2.7;
const HOLD_MS = 4300; // lama overlay tampil sebelum memudar (memudar ±0,5 detik)
const EASE_OUT = [0.22, 1, 0.36, 1];

// Batas munculnya hewan (garis lurus di panggung 320×320):
// hiu muncul dari "garis air" di bawah (setinggi riak). Buaya tidak dibatasi, ia muncul memudar di atas.
// Setelah mendarat, batas dilepas.
const CLIP_SHARK = 'inset(-300px -300px 22px -300px)'; // 22px dari bawah panggung
const CLIP_OFF = 'inset(-300px -300px -300px -300px)';

// ---------------------------------------------------------------------------
// Jalur gerak. Titik-titik di bawah dihubungkan dengan kurva mulus (Catmull-Rom), lalu hewan
// berjalan di kurva itu dengan kecepatan yang mengalir (tidak berhenti di tiap titik).
// Nilai relatif terhadap pose akhir: x/y dalam px, r = putaran (derajat). Titik terakhir = pose logo.
// ---------------------------------------------------------------------------

// Hiu: keluar dari air di kanan bawah, naik di sisi kanan tugu, melengkung lewat di belakang
// puncak, lalu menukik ke pose akhir dengan sedikit melewati (overshoot) sebelum diam.
const SHARK_POINTS = [
  { x: 128, y: 310, r: 118 },
  { x: 112, y: 70, r: 100 },
  { x: 72, y: -26, r: 58 },
  { x: 20, y: -22, r: 14 },
  { x: -5, y: 4, r: -4 },
  { x: 0, y: 0, r: 0 },
];
const SHARK_EASE = [0.33, 0.0, 0.2, 1]; // cepat keluar dari air, melambat lembut saat mendarat

// Buaya: merayap turun dari kanan atas ke kaki tugu.
const CROC_POINTS = [
  { x: 40, y: -205, r: -30 },
  { x: 43, y: -150, r: -22 },
  { x: 33, y: -100, r: -15 },
  { x: 18, y: -50, r: -7 },
  { x: 0, y: 0, r: 0 },
];
const CROC_EASE = [0.42, 0.0, 0.22, 1];

const clamp01 = (v) => Math.min(1, Math.max(0, v));
const smoothstep = (a, b, v) => {
  const t = clamp01((v - a) / (b - a));
  return t * t * (3 - 2 * t);
};

// Kurva mulus lewat semua titik, diukur per panjang lintasan (kecepatan rata).
function makeCurve(points) {
  const P = [points[0], ...points, points[points.length - 1]];
  const cr = (a, b, c, d, t) => 0.5 * (2 * b + (-a + c) * t + (2 * a - 5 * b + 4 * c - d) * t * t + (-a + 3 * b - 3 * c + d) * t * t * t);
  const raw = (u) => {
    const segs = points.length - 1;
    const f = Math.min(u * segs, segs - 1e-9);
    const i = Math.floor(f);
    const t = f - i;
    const [a, b, c, d] = [P[i], P[i + 1], P[i + 2], P[i + 3]];
    return { x: cr(a.x, b.x, c.x, d.x, t), y: cr(a.y, b.y, c.y, d.y, t), r: cr(a.r, b.r, c.r, d.r, t) };
  };
  const N = 300;
  const samples = [];
  let len = 0;
  let prev = raw(0);
  for (let k = 0; k <= N; k += 1) {
    const q = raw(k / N);
    if (k > 0) len += Math.hypot(q.x - prev.x, q.y - prev.y) + 0.4 * Math.abs(q.r - prev.r);
    samples.push({ ...q, s: len });
    prev = q;
  }
  return (p) => {
    const target = clamp01(p) * len;
    let lo = 0;
    let hi = N;
    while (hi - lo > 1) {
      const mid = (lo + hi) >> 1;
      if (samples[mid].s < target) lo = mid;
      else hi = mid;
    }
    const A = samples[lo];
    const B = samples[hi];
    const t = (target - A.s) / (B.s - A.s || 1);
    return { x: A.x + (B.x - A.x) * t, y: A.y + (B.y - A.y) * t, r: A.r + (B.r - A.r) * t };
  };
}

// Hiu: badan sedikit membesar saat keluar dari air, kepala bergoyang halus seperti berenang.
const sharkCurve = makeCurve(SHARK_POINTS);
function sharkAt(p) {
  const q = sharkCurve(p);
  const fade = Math.pow(1 - p, 2); // goyangan hilang perlahan menjelang akhir
  return {
    x: q.x,
    y: q.y,
    rotate: q.r + 4 * Math.sin(2 * Math.PI * 2 * p) * fade,
    scale: 0.88 + 0.12 * smoothstep(0, 0.45, p),
    opacity: 1,
  };
}

// Buaya: badan berayun kiri-kanan dan sedikit terangkat-turun seperti melangkah, terus mengalir.
const crocCurve = makeCurve(CROC_POINTS);
function crocAt(p) {
  const q = crocCurve(p);
  const fade = Math.pow(1 - p, 1.6);
  const step = 2 * Math.PI * 3 * p; // ±3 langkah sepanjang jalan
  return {
    x: q.x + 2 * Math.sin(step) * fade,
    y: q.y - 3 * (0.5 - 0.5 * Math.cos(2 * step)) * fade,
    rotate: q.r + 5 * Math.sin(step) * fade,
    scale: 1,
    opacity: smoothstep(0, 0.18, p),
  };
}

// Percikan air saat hiu keluar dari air (posisi relatif titik tengah tugu)
const SPLASH_AT = { x: 100, y: 128 };
const DROPS = [
  { dx: -46, dy: -58, s: 9 },
  { dx: -24, dy: -84, s: 7 },
  { dx: -4, dy: -96, s: 10 },
  { dx: 18, dy: -80, s: 8 },
  { dx: 40, dy: -60, s: 9 },
  { dx: 56, dy: -34, s: 6 },
  { dx: -60, dy: -30, s: 6 },
];
// Debu kecil saat buaya berhenti di kaki tugu
const DUST_AT = { x: 8, y: 102 };
const PUFFS = [
  { dx: -34, dy: -6, s: 16 },
  { dx: -14, dy: -14, s: 12 },
  { dx: 22, dy: -10, s: 14 },
  { dx: 38, dy: -2, s: 10 },
];

// Satu hewan: pembungkus seukuran panggung (untuk garis air), lalu 3 lapis gerak:
// masuk (mengikuti kurva) -> mendarat (pipih-memantul) -> diam-bergerak (loop kecil).
function Animal({ src, pose, z, clip, at, ease, start, duration, land, squash, idle, idleDuration, idleOrigin, reduce }) {
  const progress = useMotionValue(0);
  const x = useTransform(progress, (p) => at(p).x);
  const y = useTransform(progress, (p) => at(p).y);
  const rotate = useTransform(progress, (p) => at(p).rotate);
  const scale = useTransform(progress, (p) => at(p).scale);
  const opacity = useTransform(progress, (p) => at(p).opacity);

  useEffect(() => {
    if (reduce) return undefined;
    const controls = animate(progress, 1, { duration, delay: start, ease });
    return () => controls.stop();
  }, [reduce, progress, duration, start, ease]);

  const place = {
    position: 'absolute',
    left: `calc(50% + ${pose.x}px)`,
    top: `calc(50% + ${pose.y}px)`,
    width: pose.width,
    transform: 'translate(-50%, -50%)',
  };
  const img = <img src={src} alt="" className="block h-auto w-full max-w-none" draggable={false} />;

  if (reduce) {
    return (
      <div className="pointer-events-none relative col-start-1 row-start-1 size-80" style={{ zIndex: z }}>
        <div style={place}>{img}</div>
      </div>
    );
  }

  return (
    <motion.div
      className="pointer-events-none relative col-start-1 row-start-1 size-80"
      style={{ zIndex: z }}
      initial={{ clipPath: clip || CLIP_OFF }}
      animate={{ clipPath: CLIP_OFF }}
      transition={{ duration: 0.01, delay: land }}
    >
      <div style={place}>
        <motion.div style={{ x, y, rotate, scale, opacity, willChange: 'transform, opacity' }}>
          <motion.div
            style={{ transformOrigin: '50% 90%' }}
            animate={{ scaleX: [1, squash, 0.985, 1], scaleY: [1, 2 - squash, 1.015, 1] }}
            transition={{ duration: 0.6, delay: land - 0.08, times: [0, 0.3, 0.65, 1], ease: 'easeInOut' }}
          >
            <motion.div
              style={{ transformOrigin: idleOrigin }}
              animate={idle}
              transition={{ duration: idleDuration, delay: land + 0.5, repeat: Infinity, ease: 'easeInOut' }}
            >
              {img}
            </motion.div>
          </motion.div>
        </motion.div>
      </div>
    </motion.div>
  );
}

// Partikel kecil (tetes air / debu) yang terlempar lalu memudar
function Burst({ at, items, delay, color, gravity, className }) {
  return (
    <div className="pointer-events-none relative col-start-1 row-start-1 size-80" style={{ zIndex: 35 }}>
      {items.map((p, i) => (
        <motion.span
          key={i}
          className={`absolute rounded-full ${className || ''}`}
          style={{
            left: `calc(50% + ${at.x}px)`,
            top: `calc(50% + ${at.y}px)`,
            width: p.s,
            height: p.s,
            marginLeft: -p.s / 2,
            marginTop: -p.s / 2,
            background: color,
          }}
          initial={{ x: 0, y: 0, opacity: 0, scale: 0.4 }}
          animate={{
            x: [0, p.dx * 0.6, p.dx],
            y: [0, p.dy, p.dy + gravity],
            opacity: [0, 1, 0],
            scale: [0.4, 1, 0.7],
          }}
          transition={{ duration: 0.75, delay: delay + i * 0.02, ease: 'easeOut', times: [0, 0.45, 1] }}
        />
      ))}
    </div>
  );
}

export default function Intro({ name }) {
  const [show, setShow] = useState(true);
  const reduce = useReducedMotion();

  useEffect(() => {
    const t = setTimeout(() => setShow(false), reduce ? 1200 : HOLD_MS);
    const onKey = (e) => { if (e.key === 'Escape') setShow(false); };
    window.addEventListener('keydown', onKey);
    return () => { clearTimeout(t); window.removeEventListener('keydown', onKey); };
  }, [reduce]);

  const fullName = (name || '').replace(/\s+/g, ' ').trim();

  return (
    <AnimatePresence>
      {show ? (
        <motion.div
          key="intro"
          onClick={() => setShow(false)}
          initial={{ opacity: 1 }}
          exit={{ opacity: 0, transition: { duration: 0.5, ease: 'easeInOut' } }}
          className="fixed inset-0 z-[60] grid cursor-pointer place-items-center bg-paper"
          aria-hidden="true"
        >
          <div className="flex flex-col items-center">
            {/* Panggung 320x320 (di layar kecil diperkecil 80%) */}
            <div className="-my-8 origin-center scale-[0.8] md:my-0 md:scale-100">
              <motion.div
                style={{ willChange: 'transform' }}
                animate={reduce ? undefined : { y: [0, -6, 0] }}
                transition={{ duration: 2.6, ease: 'easeInOut', repeat: Infinity, delay: CROC_LAND + 0.4 }}
              >
                <div className="relative grid size-80 place-items-center">
                  {/* Lingkaran latar: membuat garis hitam gambar tetap terbaca di mode gelap */}
                  <motion.span
                    className="col-start-1 row-start-1 size-80 rounded-full bg-tint shadow-lg"
                    initial={{ scale: 0 }}
                    animate={{ scale: 1 }}
                    transition={reduce ? { duration: 0 } : { type: 'spring', stiffness: 160, damping: 20 }}
                  />

                  {/* Cincin emas yang melebar */}
                  {!reduce && (
                    <motion.span
                      className="col-start-1 row-start-1 size-80 rounded-full border-2 border-gold"
                      initial={{ scale: 0.7, opacity: 0 }}
                      animate={{ scale: [0.7, 1.3], opacity: [0, 0.7, 0] }}
                      transition={{ duration: 1.3, ease: 'easeOut', delay: 0.2 }}
                    />
                  )}

                  {/* Bantuan menyetel posisi: logo asli tampil samar */}
                  {ALIGN_DEBUG && (
                    <img
                      src={logo}
                      alt=""
                      className="pointer-events-none relative z-[5] col-start-1 row-start-1 block w-auto max-w-none opacity-40"
                      style={{ height: LOGO_GHOST.height, transform: `translate(${LOGO_GHOST.x}px, ${LOGO_GHOST.y}px)` }}
                    />
                  )}

                  {/* Riak air tempat hiu melompat */}
                  {!reduce && [0, 0.35].map((d) => (
                    <motion.span
                      key={d}
                      className="pointer-events-none relative z-[35] col-start-1 row-start-1 rounded-[50%] border-2 border-sky-400/70"
                      style={{ width: 90, height: 18, x: SPLASH_AT.x, y: SPLASH_AT.y + 6 }}
                      initial={{ scale: 0.2, opacity: 0 }}
                      animate={{ scale: [0.2, 1.2], opacity: [0, 0.9, 0] }}
                      transition={{ duration: 0.9, ease: 'easeOut', delay: SHARK_START + 0.05 + d }}
                    />
                  ))}

                  {/* Suro (hiu): di belakang tugu (z-10) */}
                  <Animal
                    src={suro}
                    pose={SURO_POSE}
                    z={10}
                    clip={CLIP_SHARK}
                    at={sharkAt}
                    ease={SHARK_EASE}
                    start={SHARK_START}
                    duration={SHARK_DURATION}
                    land={SHARK_LAND}
                    squash={1.06}
                    idle={{ rotate: [0, -2.5, 0, 2, 0], y: [0, -3, 0, 2, 0] }}
                    idleDuration={3.2}
                    idleOrigin="35% 85%"
                    reduce={reduce}
                  />

                  {/* Tugu: tumbuh dari bawah ke atas (z-20) */}
                  <motion.div
                    className="relative z-20 col-start-1 row-start-1"
                    initial={reduce ? { opacity: 0 } : { clipPath: 'inset(100% 0% 0% 0%)', y: 28, scale: 0.94 }}
                    animate={reduce ? { opacity: 1 } : { clipPath: 'inset(0% 0% 0% 0%)', y: 0, scale: 1 }}
                    exit={reduce ? undefined : { scale: 1.12, opacity: 0 }}
                    transition={{ duration: reduce ? 0.3 : 1, ease: EASE_OUT, delay: reduce ? 0 : 0.15 }}
                  >
                    <img
                      src={tugu}
                      alt=""
                      width="360"
                      height="560"
                      className="block h-[17.5rem] w-auto"
                    />
                  </motion.div>

                  {/* Boyo (buaya): di depan tugu (z-30) */}
                  <Animal
                    src={boyo}
                    pose={BOYO_POSE}
                    z={30}
                    at={crocAt}
                    ease={CROC_EASE}
                    start={CROC_START}
                    duration={CROC_DURATION}
                    land={CROC_LAND}
                    squash={1.07}
                    idle={{ scaleY: [1, 1.03, 1], scaleX: [1, 0.99, 1] }}
                    idleDuration={1.8}
                    idleOrigin="50% 100%"
                    reduce={reduce}
                  />

                  {/* Percikan air & debu */}
                  {!reduce && (
                    <>
                      <Burst at={SPLASH_AT} items={DROPS} delay={SHARK_START + 0.08} color="#7cc8f0" gravity={46} className="ring-2 ring-white/70" />
                      <Burst at={DUST_AT} items={PUFFS} delay={CROC_LAND - 0.05} color="rgba(120,104,72,0.4)" gravity={-6} />
                    </>
                  )}
                </div>
              </motion.div>
            </div>

            <motion.div
              className="mt-6 text-center"
              initial={{ opacity: 0, y: 12 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.5, ease: EASE_OUT, delay: reduce ? 0 : TEXT_AT }}
            >
              <div className="max-w-[90vw] break-words px-4 font-serif text-2xl font-semibold">{fullName ? `Selamat datang, ${fullName}` : 'Selamat datang'}</div>
              <div className="mt-1 text-sm text-mute">LLM Surabaya · Informasi keuangan daerah</div>
            </motion.div>
          </div>
        </motion.div>
      ) : null}
    </AnimatePresence>
  );
}

// Motion halaman Masuk / Daftar (nomor 5). Form tetap Blade biasa (submit ke route Laravel),
// file ini hanya menambahkan animasi:
// - isi kartu muncul berurutan saat halaman dibuka;
// - pindah Masuk <-> Daftar: kartu bergeser keluar, halaman berikutnya masuk dari arah sebaliknya;
// - kotak "Login gagal" bergoyang sedikit supaya terlihat.
import { animate, stagger } from 'motion';

const SWITCH_KEY = 'llm-surabaya-auth-switch';
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const root = document.documentElement;
const card = document.querySelector('[data-auth-card]');
const ease = [0.22, 1, 0.36, 1];

function readSwitch() {
  try {
    const v = sessionStorage.getItem(SWITCH_KEY);
    sessionStorage.removeItem(SWITCH_KEY);
    return v ? Number(v) : 0;
  } catch (e) {
    return 0;
  }
}

function enter() {
  const from = readSwitch(); // 1 = datang dari kanan, -1 = dari kiri, 0 = buka biasa
  const items = card.querySelectorAll('[data-anim]');
  const fields = card.querySelectorAll('form label, form button[type="submit"]');

  // Sembunyikan dulu lewat style langsung, baru lepas kelas CSS penyembunyi (mencegah kedip).
  const panel = document.querySelectorAll('[data-anim-panel]');
  [card, ...items, ...fields, ...panel].forEach((el) => { el.style.opacity = '0'; });
  root.classList.remove('auth-anim');

  if (from) {
    // Pindah dari Masuk/Daftar: seluruh kartu meluncur masuk, panel kiri tetap diam.
    items.forEach((el) => { el.style.opacity = '1'; });
    panel.forEach((el) => { el.style.opacity = '1'; });
    animate(card, { opacity: [0, 1], x: [from * 32, 0] }, { duration: 0.35, ease });
  } else {
    animate(items, { opacity: [0, 1], y: [14, 0] }, { duration: 0.45, ease, delay: stagger(0.06) });
    card.style.opacity = '1';
    if (panel.length) animate(panel, { opacity: [0, 1], y: [18, 0] }, { duration: 0.6, ease, delay: stagger(0.1, { startDelay: 0.1 }) });
  }
  animate(fields, { opacity: [0, 1], y: [8, 0] }, { duration: 0.35, ease, delay: stagger(0.05, { startDelay: from ? 0.08 : 0.2 }) });

  const photo = document.querySelector('[data-anim-photo]');
  if (photo) animate(photo, { opacity: [0, 1], scale: [1.06, 1] }, { duration: 0.9, ease });

  const failed = card.querySelector('[data-alert][role="alert"]');
  if (failed) animate(failed, { x: [0, -8, 8, -5, 5, 0] }, { duration: 0.45, delay: from ? 0.1 : 0.3 });
}

function bindSwitchLinks() {
  card.querySelectorAll('a[data-auth-switch]').forEach((link) => {
    link.addEventListener('click', (e) => {
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return; // buka di tab baru tetap normal
      e.preventDefault();
      const dir = link.dataset.authSwitch === 'register' ? 1 : -1; // ke Daftar: kartu keluar ke kiri
      try { sessionStorage.setItem(SWITCH_KEY, String(dir)); } catch (err) {}
      animate(card, { opacity: 0, x: -dir * 32 }, { duration: 0.2, ease: 'easeIn' })
        .then(() => { window.location.href = link.href; });
    });
  });
}

if (card) {
  if (reduce) {
    root.classList.remove('auth-anim');
  } else {
    enter();
    bindSwitchLinks();
  }
}

// Tombol "kembali" browser bisa menampilkan halaman dari cache dalam keadaan kartu masih tergeser.
window.addEventListener('pageshow', (e) => {
  if (e.persisted && card) {
    card.style.opacity = '';
    card.style.transform = '';
  }
});

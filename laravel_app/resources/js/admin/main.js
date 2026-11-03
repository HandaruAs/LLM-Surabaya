// Interaksi bersama area admin (halaman Blade):
// - tombol tema, menu samping di layar kecil, animasi masuk konten;
// - "pulau" React untuk halaman yang interaktif: Konfigurasi AI.
import { animate, stagger } from 'motion';
import { THEME_LABEL, getTheme, nextTheme, onThemeChange, setTheme } from '../theme';

const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/* ---------- Tema ---------- */
function paintTheme(mode) {
  document.querySelectorAll('[data-theme-icon]').forEach((el) => el.classList.toggle('hidden', el.dataset.themeIcon !== mode));
  document.querySelectorAll('[data-theme-label]').forEach((el) => { el.textContent = THEME_LABEL[mode]; });
}
document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
  btn.addEventListener('click', () => setTheme(nextTheme(getTheme())));
});
onThemeChange(paintTheme);
paintTheme(getTheme());

/* ---------- Menu samping (mobile) ---------- */
const nav = document.querySelector('[data-nav]');
const scrim = document.querySelector('[data-nav-scrim]');
function setNav(open) {
  if (!nav) return;
  nav.classList.toggle('-translate-x-full', !open);
  scrim?.classList.toggle('hidden', !open);
  document.body.style.overflow = open ? 'hidden' : '';
}
document.querySelector('[data-nav-open]')?.addEventListener('click', () => setNav(true));
document.querySelector('[data-nav-close]')?.addEventListener('click', () => setNav(false));
scrim?.addEventListener('click', () => setNav(false));
window.addEventListener('keydown', (e) => { if (e.key === 'Escape') setNav(false); });

/* ---------- Animasi masuk ---------- */
if (!reduce) {
  const items = document.querySelectorAll('[data-reveal]');
  if (items.length) {
    animate(items, { opacity: [0, 1], y: [14, 0] }, { duration: 0.5, ease: [0.22, 1, 0.36, 1], delay: stagger(0.06) });
  }
  const rows = document.querySelectorAll('[data-reveal-row]');
  if (rows.length) {
    animate(rows, { opacity: [0, 1], x: [-8, 0] }, { duration: 0.35, ease: 'easeOut', delay: stagger(0.035, { startDelay: 0.25 }) });
  }
  // Kotak pesan (berhasil / gagal) muncul dengan pantulan kecil.
  document.querySelectorAll('[data-alert]').forEach((el) => {
    animate(el, { opacity: [0, 1], scale: [0.97, 1] }, { type: 'spring', stiffness: 400, damping: 25 });
  });
}

/* ---------- Angka berjalan (data-count="123") ---------- */
document.querySelectorAll('[data-count]').forEach((el) => {
  const to = Number(el.dataset.count) || 0;
  if (reduce || to === 0) { el.textContent = to.toLocaleString('id-ID'); return; }
  animate(0, to, { duration: 1.1, ease: [0.22, 1, 0.36, 1], onUpdate: (v) => { el.textContent = Math.round(v).toLocaleString('id-ID'); } });
});

/* ---------- Pulau React ---------- */
const islands = {
  'ai-config': () => import('./AiConfig.jsx'),
};
document.querySelectorAll('[data-react]').forEach(async (el) => {
  const load = islands[el.dataset.react];
  if (!load) return;
  const [{ createRoot }, React, { MotionConfig }, mod] = await Promise.all([
    import('react-dom/client'), import('react'), import('motion/react'), load(),
  ]);
  const props = JSON.parse(el.dataset.props || '{}');
  createRoot(el).render(
    React.createElement(MotionConfig, { reducedMotion: 'user' }, React.createElement(mod.default, props)),
  );
});

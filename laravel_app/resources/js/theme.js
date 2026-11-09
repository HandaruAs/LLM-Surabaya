// Tema tampilan: 'system' (ikut perangkat), 'light', atau 'dark'.
// Disimpan di localStorage "llm-theme" dan dipasang sebagai <html data-theme="...">.
// Script kecil di partials/head.blade.php memasang tema ini sebelum halaman tampil (tanpa kedip).

const KEY = 'llm-theme';
const listeners = new Set();

export const THEMES = ['system', 'light', 'dark'];
export const THEME_LABEL = { system: 'Ikuti perangkat', light: 'Terang', dark: 'Gelap' };

export function getTheme() {
  try {
    const t = localStorage.getItem(KEY);
    return t === 'light' || t === 'dark' ? t : 'system';
  } catch (e) {
    return 'system';
  }
}

export function setTheme(mode) {
  try {
    if (mode === 'light' || mode === 'dark') localStorage.setItem(KEY, mode);
    else localStorage.removeItem(KEY);
  } catch (e) {}
  if (mode === 'light' || mode === 'dark') document.documentElement.dataset.theme = mode;
  else delete document.documentElement.dataset.theme;
  listeners.forEach((fn) => fn(mode));
}

export function nextTheme(mode) {
  return THEMES[(THEMES.indexOf(mode) + 1) % THEMES.length];
}

export function onThemeChange(fn) {
  listeners.add(fn);
  return () => listeners.delete(fn);
}

// Tema yang benar-benar tampil sekarang ('light' / 'dark').
export function resolvedTheme(mode = getTheme()) {
  if (mode === 'light' || mode === 'dark') return mode;
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

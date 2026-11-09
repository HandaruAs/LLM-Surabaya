import { useEffect, useState } from 'react';
import { getTheme, onThemeChange, setTheme } from '../theme';

// Tema yang dipilih pengguna ('system' | 'light' | 'dark').
export function useThemeMode() {
  const [mode, setMode] = useState(getTheme);
  useEffect(() => onThemeChange(setMode), []);
  return [mode, setTheme];
}


// Hitung detik sejak `active` menjadi true (dipakai di indikator "menyusun jawaban").
export function useElapsed(active) {
  const [sec, setSec] = useState(0);
  useEffect(() => {
    if (!active) { setSec(0); return undefined; }
    const start = Date.now();
    const t = setInterval(() => setSec(Math.floor((Date.now() - start) / 1000)), 1000);
    return () => clearInterval(t);
  }, [active]);
  return sec;
}

import { useEffect, useState } from 'react';
import { Toaster, toast as sonner } from 'sonner';
import { getTheme, onThemeChange, resolvedTheme } from '../../theme';

// Notifikasi singkat memakai "sonner": meluncur dari atas, menumpuk rapi, hilang sendiri.
// Komponen lain cukup memanggil useToast()('success' | 'error', 'teks').
const push = (type, text) => (type === 'error' ? sonner.error(text) : sonner.success(text));
export const useToast = () => push;

export function ToastProvider({ children }) {
  const [theme, setThemeState] = useState(() => resolvedTheme(getTheme()));
  useEffect(() => onThemeChange((mode) => setThemeState(resolvedTheme(mode))), []);

  return (
    <>
      {children}
      <Toaster
        position="top-center"
        theme={theme}
        richColors
        closeButton
        duration={3500}
        toastOptions={{ style: { fontFamily: 'var(--font-sans)', borderRadius: '14px' } }}
      />
    </>
  );
}

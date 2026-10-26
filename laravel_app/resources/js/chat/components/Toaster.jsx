import { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react';
import { AnimatePresence, motion } from 'motion/react';
import Icon from './Icon';

// Notifikasi singkat (nomor 6): muncul meluncur dari kanan, hilang sendiri setelah beberapa detik.
const ToastContext = createContext(() => {});
export const useToast = () => useContext(ToastContext);

const STYLE = {
  success: { icon: 'check', cls: 'bg-success-bg text-success-ink' },
  error: { icon: 'alert', cls: 'bg-danger-bg text-danger-ink' },
};

export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([]);
  const seq = useRef(0);

  const dismiss = useCallback((id) => setToasts((list) => list.filter((t) => t.id !== id)), []);

  const push = useCallback((type, text) => {
    const id = ++seq.current;
    // Maksimal 3 sekaligus; yang paling lama dibuang.
    setToasts((list) => [...list.slice(-2), { id, type, text }]);
  }, []);

  return (
    <ToastContext.Provider value={push}>
      {children}
      <div
        aria-live="polite"
        className="pointer-events-none fixed inset-x-4 top-20 z-50 flex flex-col items-center gap-2 sm:inset-x-auto sm:right-6 sm:items-end"
      >
        <AnimatePresence initial={false}>
          {toasts.map((t) => (
            <Toast key={t.id} toast={t} onDismiss={dismiss} />
          ))}
        </AnimatePresence>
      </div>
    </ToastContext.Provider>
  );
}

function Toast({ toast, onDismiss }) {
  const s = STYLE[toast.type] || STYLE.success;
  const [hover, setHover] = useState(false);

  // Hilang sendiri setelah 3,5 detik (ditahan selama kursor di atasnya).
  useEffect(() => {
    if (hover) return undefined;
    const t = setTimeout(() => onDismiss(toast.id), 3500);
    return () => clearTimeout(t);
  }, [hover, onDismiss, toast.id]);

  return (
    <motion.div
      layout
      role={toast.type === 'error' ? 'alert' : 'status'}
      initial={{ opacity: 0, x: 40, scale: 0.96 }}
      animate={{ opacity: 1, x: 0, scale: 1 }}
      exit={{ opacity: 0, x: 40, scale: 0.96, transition: { duration: 0.18 } }}
      transition={{ type: 'spring', stiffness: 420, damping: 32 }}
      onMouseEnter={() => setHover(true)}
      onMouseLeave={() => setHover(false)}
      className={'pointer-events-auto flex w-full max-w-sm items-start gap-2.5 rounded-xl border border-line px-3.5 py-2.5 text-sm shadow-lift ' + s.cls}
    >
      <Icon name={s.icon} className="mt-0.5 size-4" />
      <span className="flex-1 leading-5">{toast.text}</span>
      <button type="button" onClick={() => onDismiss(toast.id)} aria-label="Tutup notifikasi" className="-mr-1 cursor-pointer rounded-md p-0.5 opacity-70 transition hover:opacity-100">
        <Icon name="x" className="size-4" />
      </button>
    </motion.div>
  );
}

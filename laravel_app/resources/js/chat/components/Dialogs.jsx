import { useEffect, useRef } from 'react';
import Icon from './Icon';

// Membuka/menutup <dialog> bawaan browser mengikuti prop "open".
function useNativeDialog(open, onClose) {
  const ref = useRef(null);
  useEffect(() => {
    const d = ref.current;
    if (!d) return;
    if (open && !d.open) d.showModal();
    if (!open && d.open) d.close();
  }, [open]);
  useEffect(() => {
    const d = ref.current;
    if (!d) return undefined;
    const handle = () => onClose();
    d.addEventListener('close', handle);
    return () => d.removeEventListener('close', handle);
  }, [onClose]);
  // Klik di luar kotak (backdrop) menutup dialog.
  const onClick = (e) => { if (e.target === ref.current) onClose(); };
  return { ref, onClick };
}

export function ConfirmDialog({ confirm, onDone }) {
  const dlg = useNativeDialog(Boolean(confirm), () => onDone(false));
  return (
    <dialog {...dlg} aria-labelledby="confirmTitle" className="m-auto w-[calc(100%-2rem)] max-w-md rounded-3xl border border-line bg-card p-0 text-ink shadow-lift">
      <div className="p-6">
        <span className="grid size-12 place-items-center rounded-2xl bg-danger-bg text-danger-ink"><Icon name="trash" className="size-5" /></span>
        <h2 id="confirmTitle" className="mt-4 font-serif text-xl font-bold">{confirm?.title}</h2>
        <p className="mt-2 text-sm leading-6 text-mute">{confirm?.text}</p>
        <div className="mt-6 grid grid-cols-2 gap-2">
          <button type="button" onClick={() => onDone(false)} className="cursor-pointer rounded-xl border border-line px-4 py-2.5 text-sm font-semibold transition hover:bg-tint">Batal</button>
          <button type="button" onClick={() => onDone(true)} className="inline-flex cursor-pointer items-center justify-center gap-2 rounded-xl bg-danger-ink px-4 py-2.5 text-sm font-semibold text-card transition hover:opacity-90"><Icon name="trash" className="size-4" />Hapus</button>
        </div>
      </div>
    </dialog>
  );
}

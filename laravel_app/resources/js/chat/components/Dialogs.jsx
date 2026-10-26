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

export function FaqDialog({ open, onClose, faq }) {
  const dlg = useNativeDialog(open, onClose);
  return (
    <dialog {...dlg} aria-labelledby="faqTitle" className="m-auto w-[calc(100%-2rem)] max-w-xl overflow-hidden rounded-2xl border border-line bg-card p-0 text-ink shadow-xl">
      <div className="flex max-h-[85dvh] flex-col">
        <div className="flex items-center gap-3 border-b border-line px-5 py-4">
          <span className="grid size-10 place-items-center rounded-xl bg-tint text-brand"><Icon name="help" className="size-5" /></span>
          <h2 id="faqTitle" className="flex-1 font-serif text-lg font-semibold">Pertanyaan yang sering diajukan</h2>
          <button type="button" onClick={onClose} aria-label="Tutup" className="inline-grid size-9 cursor-pointer place-items-center rounded-lg text-mute transition hover:bg-tint hover:text-ink"><Icon name="x" className="size-5" /></button>
        </div>
        <div className="overflow-y-auto px-5 py-2">
          {faq.map(([q, a], i) => (
            <details key={q} className="group border-b border-line py-3 last:border-0" open={i === 0}>
              <summary className="flex cursor-pointer list-none items-center justify-between gap-3 font-serif text-[17px] font-semibold [&::-webkit-details-marker]:hidden">
                <span>{q}</span>
                <span className="grid size-8 shrink-0 place-items-center rounded-full bg-tint text-brand"><Icon name="chevron" className="size-4 transition group-open:rotate-90" /></span>
              </summary>
              <p className="mt-2 pb-1 leading-7 text-mute">{a}</p>
            </details>
          ))}
        </div>
      </div>
    </dialog>
  );
}

// Konfirmasi hapus. confirm = { title, text, resolve } atau null.
export function ConfirmDialog({ confirm, onDone }) {
  const dlg = useNativeDialog(Boolean(confirm), () => onDone(false));
  return (
    <dialog {...dlg} aria-labelledby="confirmTitle" className="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl border border-line bg-card p-0 text-ink shadow-xl">
      <div className="p-6">
        <div className="flex gap-4">
          <span className="grid size-11 shrink-0 place-items-center rounded-full bg-danger-bg text-danger-ink"><Icon name="trash" className="size-5" /></span>
          <div className="min-w-0">
            <h2 id="confirmTitle" className="font-serif text-lg font-semibold">{confirm?.title}</h2>
            <p className="mt-2 text-sm leading-6 text-mute">{confirm?.text}</p>
          </div>
        </div>
        <div className="mt-6 flex justify-end gap-2">
          <button type="button" onClick={() => onDone(false)} className="cursor-pointer rounded-xl border border-line px-4 py-2.5 text-sm font-semibold transition hover:bg-tint">Batal</button>
          <button type="button" onClick={() => onDone(true)} className="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-danger-ink px-4 py-2.5 text-sm font-semibold text-card transition hover:opacity-90"><Icon name="trash" className="size-4" />Hapus</button>
        </div>
      </div>
    </dialog>
  );
}

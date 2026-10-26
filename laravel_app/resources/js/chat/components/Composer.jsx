import { useLayoutEffect } from 'react';
import { AnimatePresence, motion } from 'motion/react';
import Icon from './Icon';
import { CHIPS } from '../lib';

// Kolom tulis + pesan error di atasnya (error muncul/hilang dengan animasi, nomor 6).
export default function Composer({ inputRef, value, setValue, onSend, busy, error, loggedIn, isAdmin }) {
  // Tinggi textarea mengikuti isi (maks 160px).
  useLayoutEffect(() => {
    const el = inputRef.current;
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 160) + 'px';
  }, [value, inputRef]);

  return (
    <div className="px-4 pt-2 pb-4 md:px-8 md:pb-5">
      <div className="mx-auto max-w-content">
        <AnimatePresence initial={false}>
          {error ? (
            <motion.p
              key="err"
              role="alert"
              initial={{ opacity: 0, height: 0, y: 6 }}
              animate={{ opacity: 1, height: 'auto', y: 0 }}
              exit={{ opacity: 0, height: 0, y: 6 }}
              transition={{ duration: 0.22 }}
              className="overflow-hidden"
            >
              <span className="mb-2 flex items-start gap-2 rounded-xl bg-danger-bg px-3 py-2 text-sm text-danger-ink">
                <Icon name="alert" className="mt-0.5 size-4" />{error}
              </span>
            </motion.p>
          ) : null}
        </AnimatePresence>

        <div className="rounded-2xl border border-line bg-card p-2 shadow-card transition focus-within:border-brand focus-within:ring-4 focus-within:ring-tint">
          <textarea
            ref={inputRef}
            rows={2}
            value={value}
            onChange={(e) => setValue(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter' && !e.shiftKey && !e.nativeEvent.isComposing) {
                e.preventDefault();
                onSend(value);
              }
            }}
            aria-label="Pertanyaan"
            placeholder="Tulis pertanyaan Anda tentang pajak, anggaran, atau retribusi"
            className="block max-h-40 w-full resize-none border-0 bg-transparent px-2 py-1.5 text-[15px] leading-6 text-ink outline-none placeholder:text-mute"
          />
          <div className="flex items-center justify-between gap-2 px-1 pt-1">
            <div className="flex flex-wrap gap-1.5">
              {CHIPS.map(([label, question]) => (
                <button
                  key={label}
                  type="button"
                  onClick={() => { setValue(question); inputRef.current?.focus(); }}
                  className="cursor-pointer rounded-full bg-tint px-2.5 py-1 text-xs font-medium text-brand transition hover:bg-brand hover:text-paper"
                >
                  {label}
                </button>
              ))}
            </div>
            <div className="flex items-center gap-3">
              <span className="hidden text-xs text-mute sm:inline">
                <kbd className="rounded border border-line px-1 font-sans">Enter</kbd> kirim · <kbd className="rounded border border-line px-1 font-sans">Shift</kbd>+<kbd className="rounded border border-line px-1 font-sans">Enter</kbd> baris baru
              </span>
              <motion.button
                type="button"
                aria-label="Kirim"
                disabled={busy}
                onClick={() => onSend(value)}
                whileTap={busy ? undefined : { scale: 0.92 }}
                className="grid size-10 cursor-pointer place-items-center rounded-xl bg-brand text-paper shadow-sm transition-colors hover:bg-brand-strong disabled:cursor-wait disabled:opacity-50"
              >
                <Icon name="send" className="size-5" />
              </motion.button>
            </div>
          </div>
        </div>
        <p className="mt-2 text-center text-xs leading-5 text-mute">
          Informasi bersifat panduan umum. Untuk keputusan penting, pastikan ke instansi resmi. Jangan menuliskan NIK atau nomor rekening.
          {loggedIn ? ' Riwayat tersimpan di akunmu.' : isAdmin ? ' Mode uji admin: riwayat tidak disimpan.' : ' Riwayat tidak disimpan karena kamu belum masuk.'}
        </p>
      </div>
    </div>
  );
}

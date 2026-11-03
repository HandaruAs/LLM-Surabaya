import { useLayoutEffect } from 'react';
import { AnimatePresence, motion } from 'motion/react';
import Icon from './Icon';
import { CHIPS } from '../lib';

const MAX = 1000; // sama dengan SendMessageRequest::MAX_LENGTH

// Kolom tulis melayang (efek kaca) + pesan error di atasnya.
export default function Composer({ inputRef, value, setValue, onSend, busy, error, loggedIn, isAdmin }) {
  // Tinggi textarea mengikuti isi (maks 180px).
  useLayoutEffect(() => {
    const el = inputRef.current;
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 180) + 'px';
  }, [value, inputRef]);

  const len = value.length;
  const tooLong = len > MAX;
  const canSend = !busy && value.trim() !== '' && !tooLong;

  return (
    <div className="px-3 pt-2 pb-3 md:px-8 md:pb-5">
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
              <span className="mb-2 flex items-start gap-2 rounded-2xl border border-danger/25 bg-danger-bg px-3.5 py-2.5 text-sm text-danger-ink">
                <Icon name="alert" className="mt-0.5 size-4" />{error}
              </span>
            </motion.p>
          ) : null}
        </AnimatePresence>

        <div className="glass relative rounded-[1.6rem] border border-line p-2 shadow-lift transition focus-within:border-brand/60 focus-within:shadow-[0_0_0_4px_color-mix(in_oklab,var(--brand)_15%,transparent),var(--shadow-lift)]">
          <textarea
            ref={inputRef}
            rows={1}
            value={value}
            onChange={(e) => setValue(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter' && !e.shiftKey && !e.nativeEvent.isComposing) {
                e.preventDefault();
                if (canSend) onSend(value);
              }
            }}
            aria-label="Pertanyaan"
            placeholder="Tanyakan tentang pajak, anggaran, atau retribusi Surabaya…"
            className="block max-h-[180px] min-h-[52px] w-full resize-none border-0 bg-transparent px-3 pt-3 pb-1 text-[15px] leading-6 text-ink outline-none placeholder:text-mute"
          />
          <div className="flex items-center justify-between gap-2 px-1.5 pt-1 pb-0.5">
            <div className="flex min-w-0 flex-wrap gap-1.5">
              {CHIPS.map(([label, question]) => (
                <motion.button
                  key={label}
                  type="button"
                  whileTap={{ scale: 0.95 }}
                  onClick={() => { setValue(question); inputRef.current?.focus(); }}
                  className="inline-flex cursor-pointer items-center gap-1 rounded-full border border-line bg-card px-2.5 py-1 text-xs font-medium text-mute transition hover:border-brand hover:text-brand"
                >
                  <Icon name="sparkles" className="size-3" />{label}
                </motion.button>
              ))}
            </div>
            <div className="flex items-center gap-2.5">
              <AnimatePresence>
                {len > MAX * 0.8 ? (
                  <motion.span
                    initial={{ opacity: 0, scale: 0.9 }}
                    animate={{ opacity: 1, scale: 1 }}
                    exit={{ opacity: 0, scale: 0.9 }}
                    className={'text-xs font-semibold tabular-nums ' + (tooLong ? 'text-danger-ink' : 'text-mute')}
                  >
                    {len}/{MAX}
                  </motion.span>
                ) : null}
              </AnimatePresence>
              <motion.button
                type="button"
                aria-label="Kirim"
                title="Kirim (Enter)"
                disabled={!canSend}
                onClick={() => onSend(value)}
                whileTap={canSend ? { scale: 0.9 } : undefined}
                animate={{ scale: canSend ? 1 : 0.94 }}
                className="grid size-11 cursor-pointer place-items-center rounded-2xl bg-gradient-to-br from-brand to-brand-strong text-paper shadow-glow transition-opacity disabled:cursor-not-allowed disabled:opacity-40 disabled:shadow-none"
              >
                <AnimatePresence mode="wait" initial={false}>
                  {busy ? (
                    <motion.span key="busy" initial={{ opacity: 0 }} animate={{ opacity: 1, rotate: 360 }} exit={{ opacity: 0 }} transition={{ rotate: { repeat: Infinity, duration: 1, ease: 'linear' } }}>
                      <Icon name="loader" className="size-5" />
                    </motion.span>
                  ) : (
                    <motion.span key="send" initial={{ opacity: 0, x: -6 }} animate={{ opacity: 1, x: 0 }} exit={{ opacity: 0, x: 6 }}>
                      <Icon name="send" className="size-5" />
                    </motion.span>
                  )}
                </AnimatePresence>
              </motion.button>
            </div>
          </div>
        </div>
        <p className="mt-2 px-2 text-center text-[11.5px] leading-5 text-mute">
          <span className="hidden sm:inline"><kbd className="rounded border border-line bg-card px-1 font-sans">Enter</kbd> kirim · <kbd className="rounded border border-line bg-card px-1 font-sans">Shift + Enter</kbd> baris baru · </span>
          Informasi bersifat panduan umum, jangan menuliskan NIK atau nomor rekening.
          {isAdmin ? ' Mode uji admin: riwayat tersimpan di akun admin.' : loggedIn ? ' Riwayat tersimpan di akunmu.' : ' Riwayat tidak disimpan karena kamu belum masuk.'}
        </p>
      </div>
    </div>
  );
}

import { useState } from 'react';
import { AnimatePresence, motion } from 'motion/react';
import Icon from './Icon';
import { hm, sourcesOf, sourceType } from '../lib';

const ease = [0.22, 1, 0.36, 1];

// Satu pasangan pertanyaan + jawaban. Entri baru muncul dengan fade + slide-up (nomor 1).
export default function Entry({ q, a, state, error, feedback, onFeedback, onCopy, onRetry }) {
  return (
    <motion.article
      layout="position"
      initial={{ opacity: 0, y: 16 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.35, ease }}
      className="grid gap-x-8 gap-y-4 lg:grid-cols-[minmax(0,1fr)_15rem]"
    >
      <header className="lg:col-span-2">
        <div className="min-w-0">
          <h2 className="break-words font-serif text-xl font-semibold leading-snug">{q.content}</h2>
          <time className="text-xs text-mute">{'Anda' + (q.t ? ' · ' + hm(q.t) : '')}</time>
        </div>
      </header>

      {/* Kartu "mengetik" keluar, kartu jawaban masuk (tanpa lompatan). */}
      <AnimatePresence mode="wait" initial={false}>
        {a ? (
          <Answer key="answer" a={a} feedback={feedback} onFeedback={onFeedback} onCopy={onCopy} />
        ) : state === 'typing' ? (
          <TypingCard key="typing" />
        ) : state === 'retry' ? (
          <motion.div
            key="retry"
            initial={{ opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0 }}
            className="flex flex-wrap items-center gap-3 rounded-2xl bg-danger-bg px-4 py-3 text-danger-ink lg:col-span-2"
          >
            <Icon name="alert" className="size-5" />
            <span className="flex-1 text-sm">{error}</span>
            <button type="button" onClick={onRetry} className="cursor-pointer rounded-lg border border-current px-3 py-1.5 text-sm font-semibold hover:opacity-80">
              Kirim ulang
            </button>
          </motion.div>
        ) : (
          <div key="none" className="text-sm text-mute lg:col-span-2">Tidak terjawab.</div>
        )}
      </AnimatePresence>
    </motion.article>
  );
}

// Indikator "AI sedang mengetik" (nomor 2): tiga titik naik-turun + kerangka teks.
function TypingCard() {
  return (
    <motion.div
      role="status"
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      exit={{ opacity: 0, y: -6, transition: { duration: 0.15 } }}
      transition={{ duration: 0.3, ease }}
      className="rounded-2xl border border-line bg-card p-5 shadow-card lg:col-span-2"
    >
      <div className="flex items-center gap-3 text-sm font-medium text-mute">
        <span className="flex items-end gap-1" aria-hidden="true">
          {[0, 1, 2].map((i) => (
            <motion.span
              key={i}
              className="size-2 rounded-full bg-brand"
              animate={{ y: [0, -5, 0], opacity: [0.45, 1, 0.45] }}
              transition={{ duration: 0.9, repeat: Infinity, ease: 'easeInOut', delay: i * 0.15 }}
            />
          ))}
        </span>
        Menyusun jawaban…
      </div>
      <div className="mt-4 animate-pulse space-y-2.5">
        {['w-11/12', 'w-9/12', 'w-10/12'].map((w) => (
          <div key={w} className={'h-3 rounded-full bg-tint ' + w} />
        ))}
      </div>
      <div className="mt-4 h-0.5 w-44 overflow-hidden rounded-full bg-line">
        <div className="h-full w-1/3 animate-sweep bg-brand" />
      </div>
    </motion.div>
  );
}

function Answer({ a, feedback, onFeedback, onCopy }) {
  const list = sourcesOf(a);
  const [copied, setCopied] = useState(false);

  const copy = () => {
    navigator.clipboard.writeText(a.content).then(() => {
      setCopied(true);
      onCopy();
      setTimeout(() => setCopied(false), 1500);
    }).catch(() => {});
  };

  return (
    <>
      <motion.div
        initial={{ opacity: 0, y: 12 }}
        animate={{ opacity: 1, y: 0 }}
        exit={{ opacity: 0 }}
        transition={{ duration: 0.35, ease }}
        className={'min-w-0 rounded-2xl border border-line bg-card p-5 shadow-card' + (list.length ? '' : ' lg:col-span-2')}
      >
        <div className="mb-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-brand">
          <Icon name="landmark" />Jawaban
        </div>
        {/* Teks jawaban sengaja tanpa animasi supaya nyaman dibaca. */}
        <div className="max-w-measure whitespace-pre-wrap break-words font-serif text-[17px] leading-[1.75]">{a.content}</div>

        <div className="mt-4 flex flex-wrap items-center gap-1 border-t border-line pt-3">
          <ActionBtn icon={copied ? 'check' : 'copy'} label={copied ? 'Tersalin' : 'Salin'} on={false} onClick={copy} />
          {a.id ? (
            <>
              <ActionBtn icon="up" label="Membantu" on={feedback === 'up'} onClick={() => onFeedback(feedback === 'up' ? '' : 'up')} />
              <ActionBtn icon="down" label="Kurang membantu" on={feedback === 'down'} onClick={() => onFeedback(feedback === 'down' ? '' : 'down')} />
            </>
          ) : null}
        </div>
      </motion.div>

      {list.length ? (
        <motion.aside
          initial={{ opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          exit={{ opacity: 0 }}
          transition={{ duration: 0.35, ease, delay: 0.08 }}
          className="space-y-2"
        >
          <div className="text-xs font-semibold uppercase tracking-wider text-mute">Rujukan</div>
          {list.map((name, i) => (
            <div key={i} className="flex gap-3 rounded-xl border border-line bg-card p-3">
              <span className="grid size-6 shrink-0 place-items-center rounded-md bg-tint text-[11px] font-bold text-brand">{i + 1}</span>
              <span className="min-w-0 text-sm leading-5">
                <span className="block break-words font-medium">{name}</span>
                <span className="text-xs text-mute">{sourceType(name)}</span>
              </span>
            </div>
          ))}
        </motion.aside>
      ) : null}
    </>
  );
}

function ActionBtn({ icon, label, on, onClick }) {
  return (
    <button
      type="button"
      aria-pressed={on ? 'true' : undefined}
      onClick={onClick}
      className={'inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-medium transition ' + (on ? 'bg-tint text-brand' : 'text-mute hover:bg-tint hover:text-ink')}
    >
      <Icon name={icon} />{label}
    </button>
  );
}

import { Children, memo, useEffect, useState } from 'react';
import { AnimatePresence, motion, useReducedMotion } from 'motion/react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import remarkBreaks from 'remark-breaks';
import * as Tooltip from '@radix-ui/react-tooltip';
import Icon from './Icon';
import { hm, sourcesOf, sourceType } from '../lib';
import { useElapsed } from '../hooks';

const ease = [0.22, 1, 0.36, 1];

// Jawaban yang sudah selesai "diketik" tidak dianimasikan lagi saat percakapan dibuka ulang.
const PLAYED = new Set();

// Satu pasangan pertanyaan (gelembung kanan) + jawaban AI (kartu kiri dengan avatar).
export default function Entry({ q, a, state, error, feedback, onFeedback, onCopy, onRetry, logo, showSources = false }) {
  return (
    <motion.article
      layout="position"
      initial={{ opacity: 0, y: 16 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.35, ease }}
      className="flex flex-col gap-5"
    >
      <Question q={q} />

      {/* Kartu "menyusun" keluar, kartu jawaban masuk (tanpa lompatan). */}
      <AnimatePresence mode="wait" initial={false}>
        {a ? (
          <Answer key="answer" a={a} logo={logo} feedback={feedback} onFeedback={onFeedback} onCopy={onCopy} showSources={showSources} />
        ) : state === 'typing' ? (
          <TypingCard key="typing" logo={logo} />
        ) : state === 'retry' ? (
          <motion.div
            key="retry"
            initial={{ opacity: 0, y: 8 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0 }}
            className="ml-0 flex flex-wrap items-center gap-3 rounded-2xl border border-danger/25 bg-danger-bg px-4 py-3 text-danger-ink sm:ml-12"
          >
            <span className="grid size-9 place-items-center rounded-xl bg-card/60"><Icon name="alert" className="size-5" /></span>
            <span className="flex-1 text-sm leading-5">{error}</span>
            <motion.button
              type="button"
              onClick={onRetry}
              whileTap={{ scale: 0.96 }}
              className="inline-flex cursor-pointer items-center gap-1.5 rounded-xl bg-danger-ink px-3.5 py-2 text-sm font-semibold text-card transition hover:opacity-90"
            >
              <Icon name="refresh" className="size-4" />Kirim ulang
            </motion.button>
          </motion.div>
        ) : (
          <div key="none" className="text-sm text-mute sm:ml-12">Tidak terjawab.</div>
        )}
      </AnimatePresence>
    </motion.article>
  );
}

function Question({ q }) {
  return (
    <div className="flex flex-col items-end gap-1 pl-10 sm:pl-24">
      <div className="max-w-full rounded-3xl rounded-br-lg bg-gradient-to-br from-brand to-brand-strong px-4 py-3 text-[15px] leading-6 whitespace-pre-wrap break-words text-paper shadow-glow">
        {q.content}
      </div>
      <time className="pr-1 text-[11px] text-mute">{'Anda' + (q.t ? ' · ' + hm(q.t) : '')}</time>
    </div>
  );
}

function Avatar({ logo, pulse = false }) {
  return (
    <span className="relative hidden size-9 shrink-0 sm:block">
      {pulse ? <span className="absolute inset-0 animate-ping rounded-full bg-brand/30" /> : null}
      <span className="relative grid size-9 place-items-center rounded-full border border-line bg-card shadow-card">
        <img src={logo} alt="" className="h-5 w-auto" />
      </span>
    </span>
  );
}

// Indikator "AI sedang menyusun jawaban": titik bergerak, teks berkilau, dan penghitung detik.
function TypingCard({ logo }) {
  const sec = useElapsed(true);
  const hint = sec >= 25 ? 'Model AI berjalan di server lokal, jawaban bisa memakan waktu hingga 2 menit.'
    : sec >= 8 ? 'Mencari rujukan di dokumen Pemkot Surabaya…' : 'Membaca pertanyaan Anda…';
  return (
    <motion.div
      role="status"
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      exit={{ opacity: 0, y: -6, transition: { duration: 0.15 } }}
      transition={{ duration: 0.3, ease }}
      className="flex gap-3"
    >
      <Avatar logo={logo} pulse />
      <div className="min-w-0 flex-1 overflow-hidden rounded-3xl rounded-tl-lg border border-line bg-card p-5 shadow-card">
        <div className="flex items-center gap-3">
          <span className="flex items-end gap-1" aria-hidden="true">
            {[0, 1, 2].map((i) => (
              <motion.span
                key={i}
                className="size-2 rounded-full bg-brand"
                animate={{ y: [0, -5, 0], opacity: [0.4, 1, 0.4] }}
                transition={{ duration: 0.9, repeat: Infinity, ease: 'easeInOut', delay: i * 0.15 }}
              />
            ))}
          </span>
          <span className="text-shimmer text-sm font-semibold">Menyusun jawaban</span>
          <span className="ml-auto inline-flex items-center gap-1 rounded-full bg-tint px-2 py-0.5 text-[11px] font-semibold tabular-nums text-brand">
            <Icon name="timer" className="size-3" />{sec} dtk
          </span>
        </div>
        <AnimatePresence mode="wait">
          <motion.p
            key={hint}
            initial={{ opacity: 0, y: 4 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -4 }}
            className="mt-2 text-xs text-mute"
          >
            {hint}
          </motion.p>
        </AnimatePresence>
        <div className="mt-4 space-y-2.5">
          {['w-11/12', 'w-9/12', 'w-10/12'].map((w, i) => (
            <div
              key={w}
              className={'h-3 rounded-full ' + w}
              style={{
                background: 'linear-gradient(90deg, var(--tint) 0%, var(--line) 50%, var(--tint) 100%)',
                backgroundSize: '200% 100%',
                animation: `shimmer 1.6s linear ${i * 0.15}s infinite`,
              }}
            />
          ))}
        </div>
      </div>
    </motion.div>
  );
}

// Paragraf yang diawali "Catatan:" (penutup standar dari AI) ditampilkan sebagai catatan kecil.
function Paragraph({ children }) {
  const first = Children.toArray(children)[0];
  const isNote = typeof first === 'string' && /^\s*catatan\s*:/i.test(first);
  return <p className={isNote ? 'note' : undefined}>{children}</p>;
}

const mdComponents = {
  p: Paragraph,
  a: ({ href, children }) => <a href={href} target="_blank" rel="noopener noreferrer">{children}</a>,
};

// Isi jawaban: markdown sederhana (daftar, tebal, tabel) dirender rapi; HTML mentah tidak pernah dijalankan.
const AnswerText = memo(function AnswerText({ text }) {
  return (
    <div className="answer">
      <ReactMarkdown remarkPlugins={[remarkGfm, remarkBreaks]} components={mdComponents}>{text}</ReactMarkdown>
    </div>
  );
});

// Jawaban baru "diketik" perlahan (maks ±1,6 detik); jawaban lama langsung tampil utuh.
function useTypewriter(text, enabled) {
  const reduce = useReducedMotion();
  const [n, setN] = useState(enabled && !reduce ? 0 : text.length);
  useEffect(() => {
    if (!enabled || reduce) { setN(text.length); return undefined; }
    const step = Math.max(3, Math.ceil(text.length / 95)); // ±95 frame
    let i = 0;
    const t = setInterval(() => {
      i = Math.min(text.length, i + step);
      setN(i);
      if (i >= text.length) clearInterval(t);
    }, 16);
    return () => clearInterval(t);
  }, [text, enabled, reduce]);
  return [text.slice(0, n), n >= text.length];
}

// Rujukan dokumen hanya ditampilkan di mode uji admin (showSources), tidak untuk user.
function Answer({ a, logo, feedback, onFeedback, onCopy, showSources }) {
  const list = showSources ? sourcesOf(a) : [];
  const [copied, setCopied] = useState(false);
  const playKey = a.id ?? a.lid;
  const [shown, done] = useTypewriter(a.content, Boolean(a.fresh) && !PLAYED.has(playKey));
  useEffect(() => { if (done) PLAYED.add(playKey); }, [done, playKey]);

  const copy = () => {
    navigator.clipboard.writeText(a.content).then(() => {
      setCopied(true);
      onCopy();
      setTimeout(() => setCopied(false), 1500);
    }).catch(() => {});
  };

  return (
    <motion.div
      initial={{ opacity: 0, y: 12 }}
      animate={{ opacity: 1, y: 0 }}
      exit={{ opacity: 0 }}
      transition={{ duration: 0.35, ease }}
      className="flex gap-3"
    >
      <Avatar logo={logo} />
      <div className="min-w-0 flex-1">
        <div className="mb-2 flex items-center gap-2 text-xs">
          <span className="font-semibold text-ink">Asisten Keuangan Surabaya</span>
          <span className="inline-flex items-center gap-1 rounded-full bg-tint px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-brand">
            <Icon name="sparkles" className="size-3" />AI
          </span>
          {a.t ? <span className="text-mute">· {hm(a.t)}</span> : null}
        </div>

        <div className="rounded-3xl rounded-tl-lg border border-line bg-card px-5 py-4 shadow-card">
          <AnswerText text={shown} />
          {!done ? <span className="ml-0.5 inline-block h-4 w-[3px] translate-y-0.5 animate-pulse rounded-full bg-brand" /> : null}

          <AnimatePresence>
            {done && list.length ? (
              <motion.div
                initial={{ opacity: 0, y: 6 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.3, ease }}
                className="mt-4 border-t border-dashed border-line pt-3"
              >
                <div className="mb-2 flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-mute">
                  <Icon name="book" className="size-3.5" />Rujukan dokumen
                </div>
                <div className="flex flex-wrap gap-2">
                  {list.map((name, i) => (
                    <motion.span
                      key={i}
                      initial={{ opacity: 0, scale: 0.9 }}
                      animate={{ opacity: 1, scale: 1, transition: { delay: i * 0.06 } }}
                      title={name}
                      className="inline-flex max-w-full items-center gap-2 rounded-xl border border-line bg-paper py-1.5 pr-3 pl-1.5 text-xs"
                    >
                      <span className={'grid size-6 shrink-0 place-items-center rounded-lg ' + (sourceType(name) === 'Excel' ? 'bg-success-bg text-success-ink' : 'bg-danger-bg text-danger-ink')}>
                        <Icon name={sourceType(name) === 'Excel' ? 'sheet' : 'file'} className="size-3.5" />
                      </span>
                      <span className="truncate font-medium">{name}</span>
                      <span className="shrink-0 text-mute">{sourceType(name)}</span>
                    </motion.span>
                  ))}
                </div>
              </motion.div>
            ) : null}
          </AnimatePresence>
        </div>

        <motion.div
          initial={false}
          animate={{ opacity: done ? 1 : 0 }}
          className="mt-1.5 flex flex-wrap items-center gap-0.5"
        >
          <ActionBtn icon={copied ? 'check' : 'copy'} label={copied ? 'Tersalin' : 'Salin jawaban'} on={copied} onClick={copy} />
          {a.id ? (
            <>
              <ActionBtn icon="up" label="Membantu" on={feedback === 'up'} onClick={() => onFeedback(feedback === 'up' ? '' : 'up')} />
              <ActionBtn icon="down" label="Kurang membantu" on={feedback === 'down'} onClick={() => onFeedback(feedback === 'down' ? '' : 'down')} />
            </>
          ) : null}
        </motion.div>
      </div>
    </motion.div>
  );
}

function ActionBtn({ icon, label, on, onClick }) {
  return (
    <Tooltip.Root>
      <Tooltip.Trigger asChild>
        <motion.button
          type="button"
          aria-label={label}
          aria-pressed={on ? 'true' : undefined}
          onClick={onClick}
          whileTap={{ scale: 0.85 }}
          className={'inline-grid size-8 cursor-pointer place-items-center rounded-lg transition ' + (on ? 'bg-tint text-brand' : 'text-mute hover:bg-tint hover:text-ink')}
        >
          <Icon name={icon} className="size-4" />
        </motion.button>
      </Tooltip.Trigger>
      <Tooltip.Portal>
        <Tooltip.Content sideOffset={6} className="z-50 rounded-lg bg-ink px-2.5 py-1.5 text-xs font-medium text-paper shadow-lift">
          {label}
          <Tooltip.Arrow className="fill-ink" />
        </Tooltip.Content>
      </Tooltip.Portal>
    </Tooltip.Root>
  );
}

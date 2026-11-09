import { useState } from 'react';
import { AnimatePresence, motion } from 'motion/react';
import Icon from './Icon';
import { groupSessions } from '../lib';

// Sidebar: logo, tombol obrolan baru, riwayat (dikelompokkan per tanggal) & kontak.
// Di mobile meluncur dari kiri dengan latar gelap; di desktop bisa disembunyikan.
export default function Sidebar({
  cfg, isDesktop, open, collapsed, onClose, onCollapse, sessions, titleOf, activeId, query, setQuery,
  onNew, onOpen, onDelete, onClearAll,
}) {
  const shown = isDesktop ? !collapsed : open;

  return (
    <>
      <motion.aside
        id="side"
        initial={false}
        animate={isDesktop ? { x: 0 } : { x: open ? 0 : '-105%' }}
        transition={{ type: 'spring', stiffness: 380, damping: 38 }}
        aria-hidden={shown ? undefined : 'true'}
        inert={shown ? undefined : true}
        className="fixed inset-y-0 left-0 z-40 flex w-[19rem] max-w-[88vw] flex-col overflow-hidden border-r border-line bg-card md:static md:z-auto md:w-auto md:min-w-0 md:max-w-none"
      >
        {/* Gradasi lembut di belakang bagian atas */}
        <div className="pointer-events-none absolute inset-x-0 top-0 h-48 bg-gradient-to-b from-tint to-transparent opacity-80" />

        <div className="relative flex items-center gap-3 px-4 pt-5 pb-4">
          <div className="relative">
            <span className="absolute inset-0 rounded-full bg-brand/20 blur-md" />
            <img src={cfg.logo} alt="Lambang Kota Surabaya" width="31" height="40" className="relative h-10 w-auto shrink-0 drop-shadow-sm" />
          </div>
          <div className="min-w-0 flex-1">
            <div className="truncate font-serif text-lg font-bold leading-tight">LLM Surabaya</div>
            <div className="truncate text-xs text-mute">Asisten keuangan daerah</div>
          </div>
          <button
            type="button"
            onClick={isDesktop ? onCollapse : onClose}
            aria-label={isDesktop ? 'Sembunyikan sidebar' : 'Tutup menu'}
            title={isDesktop ? 'Sembunyikan sidebar' : 'Tutup menu'}
            className="inline-grid size-9 cursor-pointer place-items-center rounded-xl text-mute transition hover:bg-tint hover:text-ink"
          >
            <Icon name={isDesktop ? 'sidebar' : 'x'} className="size-[18px]" />
          </button>
        </div>

        <div className="relative space-y-2 px-4">
          <motion.button
            type="button"
            onClick={onNew}
            whileHover={{ y: -1 }}
            whileTap={{ scale: 0.98 }}
            className="group inline-flex w-full cursor-pointer items-center gap-2.5 rounded-2xl bg-gradient-to-br from-brand to-brand-strong px-4 py-3 text-sm font-semibold text-paper shadow-glow transition-shadow"
          >
            <span className="grid size-7 place-items-center rounded-lg bg-white/15"><Icon name="newchat" className="size-4" /></span>
            <span className="flex-1 text-left">Obrolan baru</span>
            <Icon name="sparkles" className="size-4 opacity-70 transition group-hover:rotate-12 group-hover:opacity-100" />
          </motion.button>

        </div>

        {cfg.loggedIn ? (
          <div className="relative mt-5 flex min-h-0 flex-1 flex-col px-4">
            <div className="flex items-center justify-between px-1 pb-2">
              <span className="text-[11px] font-bold uppercase tracking-[0.12em] text-mute">Riwayat</span>
              {sessions.length ? (
                <button type="button" onClick={onClearAll} className="cursor-pointer rounded-md px-1.5 py-0.5 text-xs font-medium text-mute transition hover:bg-danger-bg hover:text-danger-ink">
                  Hapus semua
                </button>
              ) : null}
            </div>
            <div className="relative mb-2">
              <span className="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-mute"><Icon name="search" className="size-4" /></span>
              <input
                type="search"
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                placeholder="Cari riwayat…"
                aria-label="Cari riwayat"
                className="field rounded-xl py-2 pl-9"
              />
            </div>
            <History
              sessions={sessions}
              titleOf={titleOf}
              activeId={activeId}
              query={query}
              onOpen={onOpen}
              onDelete={onDelete}
            />
          </div>
        ) : (
          <div className="relative mt-5 flex flex-1 flex-col px-4">
            <div className="overflow-hidden rounded-2xl border border-line bg-paper/70">
              <div className="flex items-center gap-2.5 border-b border-line px-4 py-3">
                <span className="grid size-8 place-items-center rounded-lg bg-gold-soft text-gold"><Icon name="clock" className="size-4" /></span>
                <span className="text-sm font-semibold">Riwayat tidak disimpan</span>
              </div>
              <p className="px-4 pt-3 text-sm leading-6 text-mute">
                Percakapan ini hilang saat halaman ditutup. Masuk agar riwayat tersimpan dan bisa dibuka lagi.
              </p>
              <div className="grid grid-cols-2 gap-2 p-4">
                <a href={cfg.routes.login} className="inline-flex items-center justify-center gap-1.5 rounded-xl border border-line bg-card px-3 py-2 text-sm font-semibold transition hover:border-brand hover:text-brand">
                  <Icon name="login" className="size-4" />Masuk
                </a>
                <a href={cfg.routes.register} className="inline-flex items-center justify-center rounded-xl bg-brand px-3 py-2 text-sm font-semibold text-paper transition hover:bg-brand-strong">
                  Daftar
                </a>
              </div>
            </div>
          </div>
        )}

        <div className="relative mt-3 space-y-1 border-t border-line px-3 py-3">
          <Contact />
        </div>
      </motion.aside>

      <AnimatePresence>
        {!isDesktop && open ? (
          <motion.div
            key="scrim"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.2 }}
            onClick={onClose}
            className="fixed inset-0 z-30 bg-scrim backdrop-blur-[2px] md:hidden"
          />
        ) : null}
      </AnimatePresence>
    </>
  );
}

// Kontak Pemkot, bisa dibuka-tutup supaya sidebar tidak penuh.
function Contact() {
  const [open, setOpen] = useState(false);
  return (
    <div>
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        aria-expanded={open}
        className="flex w-full cursor-pointer items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition hover:bg-tint"
      >
        <span className="grid size-8 place-items-center rounded-lg bg-gold-soft text-gold"><Icon name="phone" className="size-4" /></span>
        <span className="flex-1 text-left">Hubungi kami</span>
        <motion.span animate={{ rotate: open ? 90 : 0 }}><Icon name="chevron" className="size-4 text-mute" /></motion.span>
      </button>
      <AnimatePresence initial={false}>
        {open ? (
          <motion.div
            initial={{ height: 0, opacity: 0 }}
            animate={{ height: 'auto', opacity: 1 }}
            exit={{ height: 0, opacity: 0 }}
            transition={{ duration: 0.25, ease: [0.22, 1, 0.36, 1] }}
            className="overflow-hidden"
          >
            <ul className="mx-3 mt-1 mb-2 space-y-2.5 rounded-xl bg-paper p-3 text-[13px] text-mute">
              <li className="flex gap-2.5"><Icon name="phone" className="mt-0.5 size-3.5 text-brand" />
                <div className="flex flex-col"><a className="hover:text-ink hover:underline" href="tel:+62315321444">(031) 5321444</a><a className="hover:text-ink hover:underline" href="tel:+623199277339">(031) 99277339</a></div></li>
              <li className="flex gap-2.5"><Icon name="mail" className="mt-0.5 size-3.5 text-brand" />
                <div className="flex min-w-0 flex-col"><a className="break-all hover:text-ink hover:underline" href="mailto:dinkominfo@surabaya.go.id">dinkominfo@surabaya.go.id</a><a className="break-all hover:text-ink hover:underline" href="mailto:mediacenter@surabaya.go.id">mediacenter@surabaya.go.id</a></div></li>
              <li className="flex gap-2.5"><Icon name="pin" className="mt-0.5 size-3.5 text-brand" />
                <address className="not-italic leading-relaxed">Jl. Jimerto No. 25-27, Ketabang, Kec. Genteng, Surabaya 60272</address></li>
            </ul>
          </motion.div>
        ) : null}
      </AnimatePresence>
    </div>
  );
}

function History({ sessions, titleOf, activeId, query, onOpen, onDelete }) {
  const q = query.trim().toLowerCase();
  const list = q ? sessions.filter((s) => (titleOf(s) || '').toLowerCase().includes(q)) : sessions;
  const groups = groupSessions(list);

  return (
    <nav className="scroll-thin -mx-1 flex min-h-0 flex-1 flex-col overflow-y-auto px-1 pb-2" aria-label="Riwayat percakapan">
      {!list.length ? (
        <div className="flex flex-col items-center gap-2 px-3 py-8 text-center">
          <span className="grid size-10 place-items-center rounded-xl bg-tint text-mute"><Icon name={q ? 'search' : 'message'} className="size-5" /></span>
          <span className="text-sm text-mute">{q ? 'Tidak ada riwayat yang cocok.' : 'Belum ada riwayat. Mulai bertanya!'}</span>
        </div>
      ) : null}
      {groups.map((g) => (
        <div key={g.label} className="mb-2">
          <div className="sticky top-0 z-10 bg-card/95 px-2 py-1.5 text-[11px] font-semibold text-mute backdrop-blur">{g.label}</div>
          <AnimatePresence initial={true} mode="popLayout">
            {g.items.map((c, i) => {
              const on = c.id === activeId;
              return (
                <motion.div
                  key={c.id}
                  layout
                  initial={{ opacity: 0, x: -10 }}
                  animate={{ opacity: 1, x: 0, transition: { duration: 0.25, delay: Math.min(i, 12) * 0.025 } }}
                  exit={{ opacity: 0, x: -10, transition: { duration: 0.15 } }}
                  className={'group relative flex items-center rounded-xl transition-colors ' + (on ? 'bg-tint text-brand' : 'text-ink hover:bg-paper')}
                >
                  {on ? <motion.span layoutId="hist-active" className="absolute inset-y-2 left-0 w-[3px] rounded-full bg-brand" /> : null}
                  <button type="button" onClick={() => onOpen(c.id)} className="flex min-w-0 flex-1 cursor-pointer items-center gap-2.5 px-3 py-2 text-left">
                    <Icon name="message" className={'size-4 ' + (on ? 'text-brand' : 'text-mute')} />
                    <span className="line-clamp-2 min-w-0 break-words text-[13.5px] font-medium leading-snug">{titleOf(c)}</span>
                  </button>
                  <button
                    type="button"
                    aria-label="Hapus percakapan"
                    title="Hapus percakapan"
                    onClick={() => onDelete(c.id)}
                    className={'mr-1.5 cursor-pointer rounded-lg p-1.5 text-mute transition hover:bg-danger-bg hover:text-danger-ink focus-visible:opacity-100 ' + (on ? '' : 'md:opacity-0 md:group-hover:opacity-100')}
                  >
                    <Icon name="trash" className="size-3.5" />
                  </button>
                </motion.div>
              );
            })}
          </AnimatePresence>
        </div>
      ))}
    </nav>
  );
}

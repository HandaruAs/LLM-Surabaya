import { AnimatePresence, motion } from 'motion/react';
import Icon from './Icon';
import { dayLabel } from '../lib';

// Sidebar (nomor 3): di mobile meluncur dari kiri dengan scrim, item riwayat muncul bertahap.
export default function Sidebar({
  cfg, isDesktop, open, onClose, sessions, titleOf, activeId, query, setQuery,
  onNew, onOpen, onDelete, onClearAll, onFaq,
}) {
  const shown = isDesktop || open;

  return (
    <>
      <motion.aside
        id="side"
        initial={false}
        animate={{ x: shown ? 0 : '-100%' }}
        transition={{ type: 'spring', stiffness: 380, damping: 38 }}
        aria-hidden={shown ? undefined : 'true'}
        inert={shown ? undefined : true}
        className="fixed inset-y-0 left-0 z-30 flex w-[19rem] max-w-[88vw] flex-col gap-4 overflow-y-auto border-r border-line bg-card px-4 py-5 md:static md:w-auto md:max-w-none"
      >
        <div className="flex items-center gap-3">
          <img src={cfg.logo} alt="Lambang Kota Surabaya" width="31" height="40" className="h-10 w-auto shrink-0" />
          <div>
            <div className="font-serif text-lg font-semibold leading-tight">LLM Surabaya</div>
            <div className="text-xs text-mute">Informasi keuangan daerah</div>
          </div>
        </div>

        <motion.button
          type="button"
          onClick={onNew}
          whileTap={{ scale: 0.98 }}
          className="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-brand px-4 py-2.5 text-sm font-semibold text-paper shadow-sm transition-colors hover:bg-brand-strong"
        >
          <Icon name="newchat" className="size-4" />Obrolan baru
        </motion.button>

        {cfg.loggedIn ? (
          <div className="flex min-h-[8rem] flex-1 flex-col">
            <div className="flex items-center justify-between px-1 pb-1 text-xs font-semibold uppercase tracking-wider text-mute">
              <span>Riwayat</span>
              <button type="button" onClick={onClearAll} className="cursor-pointer font-medium normal-case tracking-normal hover:text-danger-ink">Hapus semua</button>
            </div>
            <div className="relative mb-2">
              <span className="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-mute"><Icon name="search" className="size-4" /></span>
              <input
                type="search"
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                placeholder="Cari riwayat…"
                aria-label="Cari riwayat"
                className="field py-2 pl-9"
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
          // Tamu: riwayat tidak disimpan.
          <div className="flex flex-1 flex-col gap-3">
            {/* Admin: tombol kembali ke area admin ada di topbar kanan. */}
            <div className="rounded-2xl border border-line p-4">
              <div className="flex items-center gap-2 text-sm font-semibold"><Icon name="clock" className="size-4 text-gold" />{cfg.isAdmin ? 'Mode uji admin' : 'Riwayat tidak disimpan'}</div>
              <p className="mt-1.5 text-sm leading-6 text-mute">
                {cfg.isAdmin
                  ? 'Kamu sedang mencoba chatbot sebagai admin. Percakapan uji ini tidak disimpan ke riwayat dan hilang saat halaman dimuat ulang.'
                  : 'Kamu belum masuk. Percakapan ini akan hilang saat halaman ditutup atau dimuat ulang. Masuk atau daftar lewat tombol di kanan atas agar riwayat tersimpan.'}
              </p>
            </div>
          </div>
        )}

        <div className="space-y-3 border-t border-line pt-3">
          <button type="button" onClick={onFaq} className="flex w-full cursor-pointer items-center justify-between rounded-xl px-3 py-2 text-sm font-medium transition hover:bg-tint">
            <span className="flex items-center gap-2.5"><Icon name="help" className="size-4 text-brand" />FAQ</span>
            <Icon name="chevron" className="size-4 text-mute" />
          </button>

          <div className="rounded-2xl border border-line bg-tint p-4">
            <div className="text-sm font-semibold">Hubungi kami</div>
            <ul className="mt-3 space-y-2.5 text-sm text-mute">
              <li className="flex gap-2.5"><Icon name="phone" className="mt-0.5 size-3.5 text-brand" />
                <div className="flex flex-col"><a className="hover:text-ink hover:underline" href="tel:+62315321444">(031) 5321444</a><a className="hover:text-ink hover:underline" href="tel:+623199277339">(031) 99277339</a></div></li>
              <li className="flex gap-2.5"><Icon name="mail" className="mt-0.5 size-3.5 text-brand" />
                <div className="flex min-w-0 flex-col"><a className="break-all hover:text-ink hover:underline" href="mailto:dinkominfo@surabaya.go.id">dinkominfo@surabaya.go.id</a><a className="break-all hover:text-ink hover:underline" href="mailto:mediacenter@surabaya.go.id">mediacenter@surabaya.go.id</a></div></li>
              <li className="flex gap-2.5"><Icon name="pin" className="mt-0.5 size-3.5 text-brand" />
                <address className="not-italic leading-relaxed">Jl. Jimerto No. 25-27, Ketabang, Kec. Genteng, Surabaya 60272</address></li>
            </ul>
          </div>
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
            className="fixed inset-0 z-20 bg-scrim md:hidden"
          />
        ) : null}
      </AnimatePresence>
    </>
  );
}

function History({ sessions, titleOf, activeId, query, onOpen, onDelete }) {
  const q = query.trim().toLowerCase();
  const list = q ? sessions.filter((s) => (titleOf(s) || '').toLowerCase().includes(q)) : sessions;

  return (
    <nav className="flex min-h-0 flex-1 flex-col gap-0.5 overflow-y-auto" aria-label="Riwayat percakapan">
      {!list.length ? (
        <div className="px-3 py-3 text-sm text-mute">{q ? 'Tidak ada riwayat yang cocok.' : 'Belum ada riwayat.'}</div>
      ) : null}
      <AnimatePresence initial={true} mode="popLayout">
        {list.map((c, i) => {
          const on = c.id === activeId;
          return (
            <motion.div
              key={c.id}
              layout
              initial={{ opacity: 0, x: -10 }}
              animate={{ opacity: 1, x: 0, transition: { duration: 0.25, delay: Math.min(i, 12) * 0.03 } }}
              exit={{ opacity: 0, x: -10, transition: { duration: 0.15 } }}
              className={'group relative flex items-center rounded-xl transition-colors ' + (on ? 'bg-tint text-brand' : 'text-ink hover:bg-tint')}
            >
              {on ? <motion.span layoutId="hist-active" className="absolute inset-y-2 left-0 w-[3px] rounded-full bg-brand" /> : null}
              <button type="button" onClick={() => onOpen(c.id)} className="flex min-w-0 flex-1 cursor-pointer items-center gap-2.5 px-3 py-2 text-left">
                <Icon name="message" className={'size-4 ' + (on ? 'text-brand' : 'text-mute')} />
                <span className="min-w-0">
                  <span className="line-clamp-2 break-words text-sm font-medium">{titleOf(c)}</span>
                  <span className="block text-xs text-mute">{dayLabel(c.updated_at)}</span>
                </span>
              </button>
              <button
                type="button"
                aria-label="Hapus percakapan"
                onClick={() => onDelete(c.id)}
                className={'mr-1.5 cursor-pointer rounded-md p-1.5 text-mute transition hover:text-danger focus-visible:opacity-100 ' + (on ? '' : 'md:opacity-0 md:group-hover:opacity-100')}
              >
                <Icon name="trash" />
              </button>
            </motion.div>
          );
        })}
      </AnimatePresence>
    </nav>
  );
}

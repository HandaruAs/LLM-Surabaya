import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { AnimatePresence } from 'motion/react';
import Icon from './components/Icon';
import Sidebar from './components/Sidebar';
import Welcome from './components/Welcome';
import Entry from './components/Entry';
import Composer from './components/Composer';
import { ConfirmDialog, FaqDialog } from './components/Dialogs';
import { useToast } from './components/Toaster';
import { MSG, fromApi, loadJson, makeApi, saveJson, shortTitle, sourceName } from './lib';

// Logika sama dengan script lama di chat.blade.php, hanya ditulis ulang sebagai komponen React.
// - Tamu boleh bertanya, tapi riwayatnya tidak disimpan: percakapan tamu hanya ada di memori
//   halaman ini dan dikirim ulang sebagai "history".
// - User login: server yang menyimpan riwayat (GET/DELETE /api/sessions ...).

const TITLE_KEY = 'llm-surabaya-titles'; // judul lengkap per sesi (pertanyaan pertama)
const FB_KEY = 'llm-surabaya-feedback';  // suka / tidak suka per id pesan, hanya di browser
const SIDE_KEY = 'llm-surabaya-side-w', SIDE_MIN = 240, SIDE_MAX = 520, SIDE_DEF = 304;

const keyOf = (id) => (id === null ? 'draft' : String(id));
let localSeq = 0;
const localId = () => 'l' + (++localSeq);

function useIsDesktop() {
  const query = '(min-width: 768px)';
  const [match, setMatch] = useState(() => window.matchMedia(query).matches);
  useEffect(() => {
    const mq = window.matchMedia(query);
    const on = () => setMatch(mq.matches);
    mq.addEventListener('change', on);
    return () => mq.removeEventListener('change', on);
  }, []);
  return match;
}

export default function App({ cfg }) {
  const api = useMemo(() => makeApi(cfg.api, cfg.csrf), [cfg.api, cfg.csrf]);
  const toast = useToast();
  const isDesktop = useIsDesktop();

  const [sessions, setSessions] = useState([]);   // [{ id, title, updated_at }] dari GET /api/sessions
  const [activeId, setActiveState] = useState(null); // id sesi atau null untuk obrolan baru
  const [chats, setChats] = useState({});          // 'draft' | id sesi -> [{ id, role, content, sources, t }]
  const [errors, setErrors] = useState({});        // id sesi -> pesan error pertanyaan terakhir yang belum terjawab
  const [busy, setBusyState] = useState(false);
  const [loading, setLoading] = useState(false);
  const [viewKey, setViewKey] = useState(0);       // berubah saat pindah percakapan (animasi tidak diulang)
  const [query, setQuery] = useState('');
  const [formErr, setFormErr] = useState('');
  const [input, setInput] = useState('');
  const [menuOpen, setMenuOpen] = useState(false);
  const [faqOpen, setFaqOpen] = useState(false);
  const [confirm, setConfirmState] = useState(null);
  const [fullTitles, setFullTitles] = useState(() => loadJson(TITLE_KEY, {}));
  const [fb, setFb] = useState(() => loadJson(FB_KEY, {}));

  // Nilai terbaru untuk dipakai di dalam fungsi async.
  const activeRef = useRef(null);
  const busyRef = useRef(false);
  const chatsRef = useRef(chats);
  const titlesRef = useRef(fullTitles);
  const confirmRef = useRef(null);
  chatsRef.current = chats;
  titlesRef.current = fullTitles;

  const inputRef = useRef(null);
  const streamRef = useRef(null);
  const colRef = useRef(null);
  const stickRef = useRef(false); // true = layar ikut turun saat isi bertambah

  const setActive = (id) => { activeRef.current = id; setActiveState(id); };
  const setBusy = (v) => { busyRef.current = v; setBusyState(v); };

  useEffect(() => saveJson(TITLE_KEY, fullTitles), [fullTitles]);
  useEffect(() => saveJson(FB_KEY, fb), [fb]);

  const setFullTitle = useCallback((id, text) => {
    if (!text || titlesRef.current[id] === text) return;
    titlesRef.current = { ...titlesRef.current, [id]: text };
    setFullTitles(titlesRef.current);
  }, []);
  const titleOf = useCallback((c) => fullTitles[c.id] || c.title, [fullTitles]);

  const append = (key, msg) => setChats((c) => ({ ...c, [key]: [...(c[key] || []), { lid: localId(), ...msg }] }));
  const setError = (key, text) => setErrors((e) => {
    const n = { ...e };
    if (text) n[key] = text; else delete n[key];
    return n;
  });

  const confirmBox = (title, text) => new Promise((resolve) => {
    confirmRef.current = { title, text, resolve };
    setConfirmState(confirmRef.current);
  });
  const finishConfirm = useCallback((v) => {
    const c = confirmRef.current;
    if (!c) return; // dialog sudah ditutup (event close menyusul setelah klik tombol)
    confirmRef.current = null;
    setConfirmState(null);
    c.resolve(v);
  }, []);

  /* ---------- Riwayat (khusus user login) ---------- */

  // Untuk riwayat yang judulnya terlihat terpotong, ambil pertanyaan pertamanya
  // sekali saja lalu simpan, jadi tidak ada request ulang di kunjungan berikutnya.
  const fillingRef = useRef(false);
  const fillTitles = async (list) => {
    if (fillingRef.current) return;
    fillingRef.current = true;
    try {
      for (const s of list) {
        if (titlesRef.current[s.id] || !s.title || s.title.length < 36) continue;
        const r = await api('/sessions/' + s.id + '/messages');
        if (!r.ok) continue;
        const first = (r.data.data || []).find((m) => m.role === 'user');
        if (first) setFullTitle(s.id, first.content);
      }
    } finally {
      fillingRef.current = false;
    }
  };

  const refreshSessions = async () => {
    if (!cfg.loggedIn) return { ok: true, list: [] }; // tamu tidak punya riwayat tersimpan
    const r = await api('/sessions');
    if (!r.ok) return { ok: false, list: [] };
    const list = r.data.data || [];
    setSessions(list);
    // Buang judul tersimpan milik sesi yang sudah tidak ada.
    const ids = new Set(list.map((s) => String(s.id)));
    titlesRef.current = Object.fromEntries(Object.entries(titlesRef.current).filter(([k]) => ids.has(k)));
    setFullTitles(titlesRef.current);
    fillTitles(list);
    return { ok: true, list };
  };

  // Sesi baru dari server: pindahkan draf ke sesi itu dan taruh di paling atas riwayat.
  const adopt = (id, title, prevId, firstText) => {
    const key = String(id);
    if (prevId === null) {
      setChats((c) => ({ ...c, [key]: c.draft || [], draft: [] }));
      setActive(id);
    }
    setSessions((s) => [{ id, title: title || 'Obrolan baru', updated_at: new Date().toISOString() }, ...s.filter((x) => x.id !== id)]);
    const firstQ = prevId === null ? firstText : (chatsRef.current[key] || []).find((m) => m.role === 'user')?.content;
    if (firstQ && !titlesRef.current[id]) setFullTitle(id, firstQ);
    return key;
  };

  const newChat = () => {
    if (busyRef.current) return;
    setActive(null);
    setChats((c) => ({ ...c, draft: [] }));
    setFormErr('');
    setMenuOpen(false);
    setViewKey((v) => v + 1);
    inputRef.current?.focus();
  };

  const openChat = async (id, force = false) => {
    if (busyRef.current) return;
    const key = String(id);
    setActive(id);
    setFormErr('');
    setMenuOpen(false);
    setViewKey((v) => v + 1);
    if (!force && chatsRef.current[key]) return;

    setLoading(true);
    const r = await api('/sessions/' + id + '/messages');
    setLoading(false);
    if (r.status === 404) {
      await refreshSessions();
      if (activeRef.current === id) setActive(null);
      return;
    }
    if (!r.ok) {
      if (activeRef.current === id) setFormErr(r.status === 0 ? MSG.offline : MSG.generic);
      return;
    }
    const ms = (r.data.data || []).map(fromApi);
    setChats((c) => ({ ...c, [key]: ms }));
    const firstQ = ms.find((m) => m.role === 'user');
    if (firstQ) setFullTitle(id, firstQ.content);
  };

  const delChat = async (id) => {
    if (busyRef.current) return;
    const s = sessions.find((x) => x.id === id);
    const ok = await confirmBox('Hapus percakapan?', '"' + (s ? shortTitle(titleOf(s), 60) : 'Percakapan ini') + '" beserta seluruh pesannya akan dihapus dari riwayat.');
    if (!ok) return;
    const r = await api('/sessions/' + id, 'DELETE');
    if (!r.ok && r.status !== 404) { toast('error', r.status === 0 ? MSG.offline : MSG.generic); return; }
    const key = String(id);
    setSessions((list) => list.filter((x) => x.id !== id));
    setChats((c) => { const n = { ...c }; delete n[key]; return n; });
    setError(key, '');
    titlesRef.current = { ...titlesRef.current };
    delete titlesRef.current[id];
    setFullTitles(titlesRef.current);
    if (activeRef.current === id) { setActive(null); setViewKey((v) => v + 1); }
    toast('success', 'Percakapan dihapus.');
  };

  const clearAll = async () => {
    if (busyRef.current || !sessions.length) return;
    const ok = await confirmBox('Hapus semua riwayat?', 'Seluruh ' + sessions.length + ' percakapan di akunmu akan dihapus dan tidak bisa dikembalikan.');
    if (!ok) return;
    const r = await api('/sessions', 'DELETE');
    if (!r.ok) { toast('error', r.status === 0 ? MSG.offline : MSG.generic); return; }
    setSessions([]);
    setActive(null);
    setChats({});
    setErrors({});
    titlesRef.current = {};
    setFullTitles({});
    setViewKey((v) => v + 1);
    toast('success', 'Semua riwayat dihapus.');
  };

  /* ---------- Kirim pertanyaan: POST /api/chat ---------- */
  // - User login: server menyimpan pertanyaan, bertanya ke AI, lalu menyimpan jawabannya.
  // - Tamu: server hanya meneruskan ke AI; riwayat obrolan dikirim dari halaman ini.
  const send = async (raw) => {
    const text = (raw || '').trim();
    if (!text || busyRef.current) return;
    setFormErr('');
    const sid = activeRef.current;
    const key = keyOf(sid);
    const list = chatsRef.current[key] || [];
    const history = cfg.loggedIn ? undefined : list
      .filter((m) => !m.error && (m.role === 'user' || m.role === 'assistant'))
      .slice(-20)
      .map((m) => ({ role: m.role, content: m.content }));

    append(key, { role: 'user', content: text, t: Date.now() });
    setInput('');
    setBusy(true);
    stickRef.current = true;

    const r = await api('/chat', 'POST', cfg.loggedIn ? { session_id: sid, message: text } : { message: text, history });

    let target = key;
    if (r.data && r.data.session_id) target = adopt(r.data.session_id, r.data.title, sid, text);

    if (r.ok) {
      append(target, fromApi(r.data.message));
      setError(target, '');
    } else if (r.status === 503 && r.data.session_id) {
      // Pertanyaan sudah tersimpan, AI belum menjawab -> tombol "Kirim ulang".
      setError(target, MSG.aiDown);
    } else {
      // 422 / 429 / 404 / 419 / tamu gagal / server tidak terjangkau: pertanyaan TIDAK tersimpan,
      // jadi hapus dari layar dan kembalikan teksnya ke kolom input.
      setChats((c) => ({ ...c, [key]: (c[key] || []).slice(0, -1) }));
      setInput(text);
      const fieldErr = r.data.errors && Object.values(r.data.errors)[0];
      setFormErr(r.status === 0 ? MSG.offline : (fieldErr && fieldErr[0]) || r.data.message || MSG.generic);
      if (r.status === 404) {
        setActive(null);
        setChats((c) => ({ ...c, draft: [] }));
        setViewKey((v) => v + 1);
        await refreshSessions();
      }
    }

    setBusy(false);
    inputRef.current?.focus();
  };

  // Kirim ulang pertanyaan terakhir yang belum terjawab: POST /api/sessions/{id}/retry.
  const retry = async () => {
    const id = activeRef.current;
    if (busyRef.current || id === null) return;
    const key = String(id);
    setFormErr('');
    setBusy(true);
    stickRef.current = true;

    const r = await api('/sessions/' + id + '/retry', 'POST');

    if (r.ok) {
      append(key, fromApi(r.data.message));
      setError(key, '');
      setSessions((s) => [...s.filter((x) => x.id === id), ...s.filter((x) => x.id !== id)]);
    } else if (r.status === 422 || r.status === 409) {
      // Sudah terjawab / sedang diproses di tab lain: muat ulang pesan dari server.
      setError(key, '');
      setBusy(false);
      return openChat(id, true);
    } else if (r.status === 404) {
      setChats((c) => { const n = { ...c }; delete n[key]; return n; });
      setActive(null);
      setViewKey((v) => v + 1);
      await refreshSessions();
    } else {
      setError(key, r.status === 0 ? MSG.offline : r.status === 503 ? MSG.aiDown : (r.data.message || MSG.generic));
    }

    setBusy(false);
  };

  /* ---------- Muat awal ---------- */
  const started = useRef(false);
  useEffect(() => {
    if (started.current) return; // StrictMode (dev) menjalankan efek dua kali
    started.current = true;
    if (!cfg.loggedIn) return;
    (async () => {
      const { ok, list } = await refreshSessions();
      if (!ok) setFormErr(MSG.offline);
      if (list.length) await openChat(list[0].id);
    })();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  /* ---------- Scroll ---------- */
  const key = keyOf(activeId);
  const ms = chats[key] || [];

  // Pindah percakapan: langsung ke bawah; layar sambutan dibaca dari atas.
  useEffect(() => {
    const s = streamRef.current;
    if (!s) return;
    stickRef.current = ms.length > 0;
    s.scrollTop = ms.length ? s.scrollHeight : 0;
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [viewKey, loading]);

  // Isi bertambah (pesan baru, jawaban masuk): ikut turun selama user tidak sedang membaca ke atas.
  useEffect(() => {
    const s = streamRef.current, c = colRef.current;
    if (!s || !c) return undefined;
    const ro = new ResizeObserver(() => { if (stickRef.current) s.scrollTop = s.scrollHeight; });
    ro.observe(c);
    return () => ro.disconnect();
  }, []);
  const onScroll = () => {
    const s = streamRef.current;
    if (s && ms.length) stickRef.current = s.scrollHeight - s.scrollTop - s.clientHeight < 120;
  };

  /* ---------- Sidebar: tutup dengan Esc (mobile) & ubah lebar (desktop) ---------- */
  useEffect(() => {
    if (!menuOpen) return undefined;
    const onKey = (e) => { if (e.key === 'Escape') setMenuOpen(false); };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [menuOpen]);

  const sideW = useRef(SIDE_DEF);
  const setSide = (px) => {
    px = Math.max(SIDE_MIN, Math.min(SIDE_MAX, Math.round(px)));
    document.documentElement.style.setProperty('--side-w', px + 'px');
    sideW.current = px;
  };
  useEffect(() => {
    try { const s = parseInt(localStorage.getItem(SIDE_KEY), 10); if (s) setSide(s); } catch (e) {}
  }, []);
  const saveSide = () => { try { localStorage.setItem(SIDE_KEY, sideW.current); } catch (e) {} };
  const onResizeStart = (e) => {
    e.preventDefault();
    const rz = e.currentTarget;
    rz.setPointerCapture(e.pointerId);
    document.body.style.userSelect = 'none';
    document.body.style.cursor = 'col-resize';
    const move = (ev) => setSide(ev.clientX);
    const up = () => {
      rz.removeEventListener('pointermove', move);
      rz.removeEventListener('pointerup', up);
      rz.removeEventListener('pointercancel', up);
      document.body.style.userSelect = '';
      document.body.style.cursor = '';
      saveSide();
    };
    rz.addEventListener('pointermove', move);
    rz.addEventListener('pointerup', up);
    rz.addEventListener('pointercancel', up);
  };

  /* ---------- Tampilan ---------- */
  const active = sessions.find((s) => s.id === activeId);
  const questions = ms.filter((m) => m.role === 'user').length;
  const title = active ? shortTitle(titleOf(active)) : ms.length ? shortTitle(ms[0].content) : 'Obrolan baru';
  const firstSource = ms.find((m) => m.role === 'assistant' && m.sources && m.sources.length);
  const subtitle = questions
    ? questions + ' pertanyaan' + (firstSource ? ' · rujukan: ' + sourceName(firstSource.sources[0]) : '')
    : 'Tanya jawab keuangan daerah Kota Surabaya';

  const entries = [];
  ms.forEach((m, i) => {
    if (m.role !== 'user') return;
    const a = ms[i + 1] && ms[i + 1].role === 'assistant' ? ms[i + 1] : null;
    // "last" = entri ini adalah pesan paling akhir di sesi (hanya itu yang bisa dikirim ulang).
    const last = (a ? i + 1 : i) === ms.length - 1;
    const state = a ? 'answered' : busy && last ? 'typing' : last && activeId !== null ? 'retry' : 'none';
    entries.push({ k: m.id ? 'm' + m.id : m.lid, q: m, a, state });
  });

  return (
    <div className="relative grid h-dvh grid-cols-1 md:grid-cols-[var(--side-w)_minmax(0,1fr)]">
      <Sidebar
        cfg={cfg}
        isDesktop={isDesktop}
        open={menuOpen}
        onClose={() => setMenuOpen(false)}
        sessions={sessions}
        titleOf={titleOf}
        activeId={activeId}
        query={query}
        setQuery={setQuery}
        onNew={newChat}
        onOpen={(id) => openChat(id)}
        onDelete={delChat}
        onClearAll={clearAll}
        onFaq={() => { setMenuOpen(false); setFaqOpen(true); }}
      />

      <div
        role="separator"
        aria-orientation="vertical"
        aria-label="Ubah lebar sidebar"
        onPointerDown={onResizeStart}
        onDoubleClick={() => { setSide(SIDE_DEF); saveSide(); }}
        className="absolute inset-y-0 z-20 hidden w-1.5 cursor-col-resize touch-none transition-colors hover:bg-brand/40 active:bg-brand/60 md:left-[calc(var(--side-w)-3px)] md:block"
      />

      {/* ================= Area percakapan ================= */}
      <section className="flex min-h-0 min-w-0 flex-col">
        <header className="relative flex items-center gap-3 border-b border-line bg-card px-4 py-3.5 md:px-6">
          <button type="button" onClick={() => setMenuOpen(true)} aria-label="Buka menu" className="inline-grid size-9 cursor-pointer place-items-center rounded-lg text-mute transition hover:bg-tint hover:text-ink md:hidden">
            <Icon name="menu" className="size-5" />
          </button>
          <div className="min-w-0 flex-1">
            <h1 className="truncate font-serif text-lg font-semibold">{title}</h1>
            <p className="truncate text-xs text-mute">{subtitle}</p>
          </div>
          {cfg.loggedIn ? (
            <div className="flex shrink-0 items-center gap-2 sm:gap-3">
              <div className="hidden items-center gap-2.5 sm:flex">
                <span className="grid size-8 shrink-0 place-items-center rounded-full bg-tint text-xs font-bold text-brand">{cfg.user.initials}</span>
                <div className="max-w-[12rem] leading-tight">
                  <div className="truncate text-sm font-semibold">{cfg.user.name}</div>
                  <div className="truncate text-xs text-mute">{cfg.user.email}</div>
                </div>
              </div>
              <form method="POST" action={cfg.routes.logout}>
                <input type="hidden" name="_token" value={cfg.csrf} />
                <button type="submit" className="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-line px-3 py-2 text-sm font-semibold text-mute transition hover:border-danger hover:text-danger-ink">
                  <Icon name="logout" className="size-4" />Keluar
                </button>
              </form>
            </div>
          ) : cfg.admin ? (
            // Admin dari menu "Chatbot AI": tampilkan akun admin, bukan tombol Masuk/Daftar.
            <div className="flex shrink-0 items-center gap-2 sm:gap-3">
              <div className="hidden items-center gap-2.5 sm:flex">
                <span className="grid size-8 shrink-0 place-items-center rounded-full bg-tint text-xs font-bold text-brand">{cfg.admin.initials}</span>
                <div className="max-w-[12rem] leading-tight">
                  <div className="truncate text-sm font-semibold">{cfg.admin.name}</div>
                  <div className="truncate text-xs text-mute">Admin</div>
                </div>
              </div>
              <a href={cfg.routes.admin} className="inline-flex items-center gap-1.5 rounded-xl bg-brand px-3 py-2 text-sm font-semibold text-paper shadow-sm transition hover:bg-brand-strong">
                <Icon name="arrow" className="size-4 rotate-180" />Area admin
              </a>
              <form method="POST" action={cfg.routes.adminLogout}>
                <input type="hidden" name="_token" value={cfg.csrf} />
                <button type="submit" aria-label="Keluar" className="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-line px-3 py-2 text-sm font-semibold text-mute transition hover:border-danger hover:text-danger-ink">
                  <Icon name="logout" className="size-4" /><span className="hidden sm:inline">Keluar</span>
                </button>
              </form>
            </div>
          ) : (
            <div className="flex shrink-0 items-center gap-2">
              <a href={cfg.routes.login} className="inline-flex items-center gap-1.5 rounded-xl border border-line px-3 py-2 text-sm font-semibold transition hover:bg-tint"><Icon name="login" className="size-4" />Masuk</a>
              <a href={cfg.routes.register} className="rounded-xl bg-brand px-3 py-2 text-sm font-semibold text-paper shadow-sm transition hover:bg-brand-strong">Daftar</a>
            </div>
          )}
          <span className="absolute -bottom-px left-0 h-[3px] w-16 bg-gold" />
        </header>

        <div ref={streamRef} onScroll={onScroll} className="flex-1 overflow-y-auto px-4 py-8 md:px-8">
          <div ref={colRef} className="mx-auto flex max-w-content flex-col gap-10">
            {loading ? (
              <div className="text-sm text-mute">Memuat percakapan…</div>
            ) : !ms.length ? (
              <Welcome key={'w' + viewKey} userName={cfg.user?.name || cfg.admin?.name} onAsk={send} />
            ) : (
              // initial={false}: pesan lama tidak dianimasikan saat percakapan dibuka, hanya pesan baru.
              <AnimatePresence key={'v' + viewKey} initial={false}>
                {entries.map(({ k, q, a, state }) => (
                  <Entry
                    key={k}
                    q={q}
                    a={a}
                    state={state}
                    error={errors[key] || MSG.unanswered}
                    feedback={a && a.id ? fb[a.id] || '' : ''}
                    onFeedback={(v) => setFb((all) => { const n = { ...all }; if (v) n[a.id] = v; else delete n[a.id]; return n; })}
                    onCopy={() => toast('success', 'Jawaban disalin.')}
                    onRetry={retry}
                  />
                ))}
              </AnimatePresence>
            )}
          </div>
        </div>

        <Composer
          inputRef={inputRef}
          value={input}
          setValue={setInput}
          onSend={send}
          busy={busy}
          error={formErr}
          loggedIn={cfg.loggedIn}
          isAdmin={Boolean(cfg.admin)}
        />
      </section>

      <FaqDialog open={faqOpen} onClose={() => setFaqOpen(false)} faq={cfg.faq} />
      <ConfirmDialog confirm={confirm} onDone={finishConfirm} />
    </div>
  );
}


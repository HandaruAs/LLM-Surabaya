import { useEffect, useMemo, useRef, useState } from 'react';
import { AnimatePresence, Reorder, motion, useDragControls } from 'motion/react';
import { Check, ChevronDown, CircleAlert, Eye, GripVertical, LoaderCircle, Pencil, Plus, Save, Sparkles, Trash2, UserRound, X } from 'lucide-react';

// Halaman Konfigurasi AI. Data yang disimpan sama persis dengan versi lama:
// PUT /admin/ai-config { role, rules: [{text, on}], top_k, num_ctx, show_context, think }.
let seq = 0;
const withId = (r) => ({ id: ++seq, text: r.text, on: Boolean(r.on) });
const ease = [0.22, 1, 0.36, 1];

function Switch({ on, onChange, label }) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={on}
      aria-label={label}
      onClick={() => onChange(!on)}
      className={'relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full p-0.5 transition-colors ' + (on ? 'bg-brand' : 'bg-line')}
    >
      <motion.span layout transition={{ type: 'spring', stiffness: 600, damping: 35 }} className={'size-5 rounded-full bg-card shadow ' + (on ? 'ml-auto' : '')} />
    </button>
  );
}

function RuleRow({ rule, editing, onEdit, onSave, onCancel, onToggle, onDelete }) {
  const controls = useDragControls();
  const [draft, setDraft] = useState(rule.text);
  const [confirm, setConfirm] = useState(false);
  const inputRef = useRef(null);
  useEffect(() => { if (editing) { setDraft(rule.text); setTimeout(() => inputRef.current?.focus(), 0); } }, [editing, rule.text]);

  const save = () => { if (draft.trim()) onSave(draft.trim()); };

  return (
    <Reorder.Item
      value={rule}
      dragListener={false}
      dragControls={controls}
      initial={{ opacity: 0, y: -6 }}
      animate={{ opacity: 1, y: 0 }}
      exit={{ opacity: 0, x: -20, transition: { duration: 0.18 } }}
      whileDrag={{ scale: 1.02, boxShadow: '0 18px 40px -12px rgb(0 0 0 / 0.25)' }}
      className={'group relative flex min-h-16 items-center gap-3 rounded-2xl border bg-card px-3 py-2.5 transition-colors ' + (rule.on ? 'border-line' : 'border-dashed border-line opacity-80')}
    >
      <button
        type="button"
        aria-label="Geser untuk mengubah urutan"
        title="Geser untuk mengubah urutan"
        onPointerDown={(e) => controls.start(e)}
        className="cursor-grab touch-none rounded-lg p-1 text-mute transition hover:bg-tint hover:text-ink active:cursor-grabbing"
      >
        <GripVertical className="size-4" />
      </button>

      {confirm ? (
        <>
          <span className="flex-1 text-sm font-medium text-danger-ink">Hapus aturan ini?</span>
          <button type="button" onClick={onDelete} className="cursor-pointer rounded-xl bg-danger-ink px-3 py-1.5 text-xs font-semibold text-card">Hapus</button>
          <button type="button" onClick={() => setConfirm(false)} className="cursor-pointer rounded-xl border border-line px-3 py-1.5 text-xs font-semibold hover:bg-tint">Batal</button>
        </>
      ) : editing ? (
        <>
          <input
            ref={inputRef}
            value={draft}
            maxLength={500}
            onChange={(e) => setDraft(e.target.value)}
            onKeyDown={(e) => { if (e.key === 'Enter') save(); if (e.key === 'Escape') onCancel(); }}
            className="field min-w-0 flex-1 py-2"
          />
          <button type="button" onClick={save} aria-label="Simpan edit" className="grid size-9 cursor-pointer place-items-center rounded-xl bg-brand text-paper"><Check className="size-4" /></button>
          <button type="button" onClick={onCancel} aria-label="Batalkan edit" className="grid size-9 cursor-pointer place-items-center rounded-xl border border-line text-mute hover:bg-tint"><X className="size-4" /></button>
        </>
      ) : (
        <>
          <Switch on={rule.on} onChange={onToggle} label="Aktifkan aturan" />
          <span className={'min-w-0 flex-1 text-sm leading-6 ' + (rule.on ? '' : 'text-mute line-through')}>{rule.text}</span>
          <div className="flex items-center gap-0.5 opacity-100 transition md:opacity-0 md:group-focus-within:opacity-100 md:group-hover:opacity-100">
            <button type="button" onClick={onEdit} aria-label="Edit aturan" title="Edit aturan" className="grid size-9 cursor-pointer place-items-center rounded-xl text-mute hover:bg-tint hover:text-ink"><Pencil className="size-4" /></button>
            <button type="button" onClick={() => setConfirm(true)} aria-label="Hapus aturan" title="Hapus aturan" className="grid size-9 cursor-pointer place-items-center rounded-xl text-mute hover:bg-danger-bg hover:text-danger-ink"><Trash2 className="size-4" /></button>
          </div>
        </>
      )}
    </Reorder.Item>
  );
}

export default function AiConfig({ config, saveUrl, csrf }) {
  const [role, setRole] = useState(config.role || '');
  const [rules, setRules] = useState(() => (config.rules || []).map(withId));
  const [topK, setTopK] = useState(Number(config.top_k) || 3);
  const [numCtx, setNumCtx] = useState(Number(config.num_ctx) || 512);
  const [editing, setEditing] = useState(null);
  const [newRule, setNewRule] = useState('');
  const [err, setErr] = useState('');
  const [status, setStatus] = useState({ type: '', text: '' });
  const [saving, setSaving] = useState(false);
  const [advanced, setAdvanced] = useState(false);
  const [preview, setPreview] = useState(false);

  const pack = () => ({
    role: role.trim(),
    rules: rules.map((r) => ({ text: r.text, on: r.on })),
    top_k: Number(topK),
    num_ctx: Number(numCtx),
    // Nilai lain dari konfigurasi lama tetap dipertahankan.
    show_context: Boolean(config.show_context),
    think: Boolean(config.think),
  });
  const [saved, setSaved] = useState(() => JSON.stringify(pack()));
  const dirty = JSON.stringify(pack()) !== saved;

  useEffect(() => {
    const onLeave = (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } };
    window.addEventListener('beforeunload', onLeave);
    return () => window.removeEventListener('beforeunload', onLeave);
  }, [dirty]);

  const active = rules.filter((r) => r.on);
  const prompt = useMemo(() => {
    const parts = [];
    if (role.trim()) parts.push(role.trim());
    if (active.length) parts.push('Aturan jawaban:\n' + active.map((r) => '- ' + r.text).join('\n'));
    return parts.join('\n\n');
  }, [role, active]);

  const add = () => {
    const v = newRule.trim();
    if (!v) { setErr('Tulis aturannya dulu.'); return; }
    setRules((list) => [...list, withId({ text: v, on: true })]);
    setNewRule('');
    setErr('');
  };

  const save = async () => {
    setSaving(true);
    setStatus({ type: '', text: '' });
    try {
      const res = await fetch(saveUrl, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        body: JSON.stringify(pack()),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        setStatus({ type: 'error', text: data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Gagal menyimpan.') });
      } else {
        setSaved(JSON.stringify(pack()));
        setStatus({ type: 'ok', text: 'Tersimpan. Chatbot langsung memakai pengaturan ini.' });
      }
    } catch (e) {
      setStatus({ type: 'error', text: 'Gagal menyimpan. Cek koneksi ke server Laravel.' });
    }
    setSaving(false);
  };

  return (
    <div className="grid max-w-6xl gap-6 pb-24 xl:grid-cols-[minmax(0,1fr)_22rem]">
      <div className="space-y-6">
        {/* Peran AI */}
        <motion.section initial={{ opacity: 0, y: 14 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.45, ease }} className="rounded-3xl border border-line bg-card p-6 shadow-card">
          <div className="flex items-start gap-3">
            <span className="grid size-11 shrink-0 place-items-center rounded-2xl bg-tint text-brand"><UserRound className="size-5" /></span>
            <div className="min-w-0 flex-1">
              <h2 className="font-serif text-lg font-bold">Peran AI</h2>
              <p className="text-sm text-mute">Satu kalimat tentang siapa AI ini dan untuk siapa.</p>
            </div>
          </div>
          <textarea
            id="role"
            rows={2}
            value={role}
            maxLength={500}
            onChange={(e) => setRole(e.target.value)}
            placeholder="Contoh: Kamu adalah asisten edukasi keuangan untuk masyarakat Surabaya."
            className="field mt-4 resize-none leading-6"
          />
          <div className="mt-1.5 text-right text-xs text-mute tabular-nums">{role.length}/500</div>
        </motion.section>

        {/* Aturan jawaban */}
        <motion.section initial={{ opacity: 0, y: 14 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.45, ease, delay: 0.06 }} className="rounded-3xl border border-line bg-card p-6 shadow-card">
          <div className="flex flex-wrap items-start gap-3">
            <span className="grid size-11 shrink-0 place-items-center rounded-2xl bg-gold-soft text-gold"><Sparkles className="size-5" /></span>
            <div className="min-w-0 flex-1">
              <h2 className="font-serif text-lg font-bold">Aturan jawaban</h2>
              <p className="text-sm text-mute">Aktifkan dengan sakelar, geser untuk mengubah urutan. Aturan nonaktif tetap tersimpan tapi tidak dikirim ke AI.</p>
            </div>
            <motion.span key={active.length + '/' + rules.length} initial={{ scale: 0.8 }} animate={{ scale: 1 }} className="rounded-full bg-tint px-3 py-1 text-xs font-semibold text-brand">
              {active.length} dari {rules.length} aktif
            </motion.span>
          </div>

          <Reorder.Group axis="y" values={rules} onReorder={setRules} className="mt-5 space-y-2">
            <AnimatePresence initial={false}>
              {rules.map((r) => (
                <RuleRow
                  key={r.id}
                  rule={r}
                  editing={editing === r.id}
                  onEdit={() => setEditing(r.id)}
                  onCancel={() => setEditing(null)}
                  onSave={(text) => { setRules((l) => l.map((x) => (x.id === r.id ? { ...x, text } : x))); setEditing(null); }}
                  onToggle={(on) => setRules((l) => l.map((x) => (x.id === r.id ? { ...x, on } : x)))}
                  onDelete={() => setRules((l) => l.filter((x) => x.id !== r.id))}
                />
              ))}
            </AnimatePresence>
          </Reorder.Group>
          {!rules.length ? (
            <div className="mt-5 rounded-2xl border border-dashed border-line px-4 py-8 text-center text-sm text-mute">Belum ada aturan. Tambahkan aturan pertama di bawah.</div>
          ) : null}

          <div className="mt-4 flex gap-2">
            <input
              value={newRule}
              maxLength={500}
              onChange={(e) => { setNewRule(e.target.value); setErr(''); }}
              onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); add(); } }}
              placeholder="Contoh: Selalu sebut nama dinas yang relevan"
              className="field min-w-0 flex-1"
            />
            <motion.button whileTap={{ scale: 0.96 }} type="button" onClick={add} className="inline-flex shrink-0 cursor-pointer items-center gap-1.5 rounded-xl border border-brand px-4 text-sm font-semibold text-brand transition hover:bg-tint">
              <Plus className="size-4" />Tambah
            </motion.button>
          </div>
          <AnimatePresence>
            {err ? (
              <motion.p initial={{ opacity: 0, y: -4 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0 }} role="alert" className="mt-2 flex items-center gap-1.5 text-sm text-danger-ink">
                <CircleAlert className="size-4" />{err}
              </motion.p>
            ) : null}
          </AnimatePresence>
        </motion.section>

        {/* Lanjutan */}
        <motion.section initial={{ opacity: 0, y: 14 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.45, ease, delay: 0.12 }} className="overflow-hidden rounded-3xl border border-line bg-card shadow-card">
          <button type="button" onClick={() => setAdvanced((v) => !v)} aria-expanded={advanced} className="flex w-full cursor-pointer items-center gap-3 px-6 py-5 text-left">
            <div className="min-w-0 flex-1">
              <h2 className="font-serif text-lg font-bold">Pengaturan lanjutan</h2>
              <p className="text-sm text-mute">Jumlah rujukan dokumen dan panjang konteks model. Biarkan bawaan bila ragu.</p>
            </div>
            <motion.span animate={{ rotate: advanced ? 180 : 0 }}><ChevronDown className="size-5 text-mute" /></motion.span>
          </button>
          <AnimatePresence initial={false}>
            {advanced ? (
              <motion.div initial={{ height: 0 }} animate={{ height: 'auto' }} exit={{ height: 0 }} transition={{ duration: 0.3, ease }} className="overflow-hidden">
                <div className="grid gap-6 border-t border-line px-6 py-5 md:grid-cols-2">
                  <label className="block">
                    <span className="flex items-center justify-between text-sm font-semibold">
                      Potongan dokumen dirujuk <span className="rounded-lg bg-tint px-2 py-0.5 text-brand tabular-nums">{topK}</span>
                    </span>
                    <input type="range" min={1} max={10} value={topK} onChange={(e) => setTopK(Number(e.target.value))} className="mt-3 w-full accent-[var(--brand)]" />
                    <span className="mt-1 block text-xs text-mute">Makin banyak, jawaban makin lengkap tapi lebih lambat. Bawaan: 3.</span>
                  </label>
                  <label className="block">
                    <span className="text-sm font-semibold">Panjang konteks model</span>
                    <select value={numCtx} onChange={(e) => setNumCtx(Number(e.target.value))} className="field mt-2">
                      {[512, 1024, 2048, 4096, 8192].map((n) => <option key={n} value={n}>{n.toLocaleString('id-ID')} token{n === 512 ? ' (bawaan)' : ''}</option>)}
                    </select>
                    <span className="mt-1 block text-xs text-mute">Nilai besar butuh RAM lebih banyak di server AI.</span>
                  </label>
                </div>
              </motion.div>
            ) : null}
          </AnimatePresence>
        </motion.section>
      </div>

      {/* Pratinjau instruksi */}
      <motion.aside initial={{ opacity: 0, y: 14 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.45, ease, delay: 0.1 }} className="space-y-4 xl:sticky xl:top-6 xl:self-start">
        <div className="overflow-hidden rounded-3xl bg-gradient-to-br from-night to-night-2 p-5 text-white shadow-lift">
          <div className="flex items-center gap-2 text-sm font-semibold"><Eye className="size-4" />Instruksi yang dikirim ke AI</div>
          <p className="mt-1 text-xs text-white/60">Ditambahkan setelah instruksi dasar chatbot.</p>
          <pre className="mt-4 max-h-80 overflow-auto whitespace-pre-wrap rounded-2xl bg-black/25 p-4 font-mono text-[12px] leading-5 text-white/85 ring-1 ring-white/10">
            {prompt || '— Belum ada instruksi tambahan —'}
          </pre>
          <button type="button" onClick={() => setPreview((v) => !v)} className="mt-3 text-xs font-semibold text-white/70 underline-offset-4 hover:text-white hover:underline">
            {preview ? 'Sembunyikan' : 'Apa artinya?'}
          </button>
          <AnimatePresence>
            {preview ? (
              <motion.p initial={{ opacity: 0, height: 0 }} animate={{ opacity: 1, height: 'auto' }} exit={{ opacity: 0, height: 0 }} className="overflow-hidden text-xs leading-5 text-white/70">
                Teks ini digabung dengan aturan bawaan chatbot (bahasa sederhana, tanpa rekomendasi produk investasi, dan catatan di akhir jawaban). Perubahan berlaku mulai pertanyaan berikutnya.
              </motion.p>
            ) : null}
          </AnimatePresence>
        </div>
      </motion.aside>

      {/* Bar simpan melayang */}
      <div className="fixed inset-x-4 bottom-4 z-20 md:left-[calc(17.5rem+2rem)] md:right-8">
        <motion.div
          animate={{ y: 0, opacity: 1 }}
          initial={{ y: 30, opacity: 0 }}
          className={'glass mx-auto flex max-w-6xl flex-wrap items-center gap-3 rounded-2xl border p-3 pl-4 shadow-lift transition-colors ' + (dirty ? 'border-gold' : 'border-line')}
        >
          <span className="relative flex size-2.5">
            {dirty ? <span className="absolute inline-flex size-full animate-ping rounded-full bg-gold opacity-60" /> : null}
            <span className={'relative inline-flex size-2.5 rounded-full ' + (dirty ? 'bg-gold' : status.type === 'error' ? 'bg-danger' : 'bg-brand')} />
          </span>
          <p role="status" aria-live="polite" className={'min-w-0 flex-1 text-sm ' + (status.type === 'error' ? 'text-danger-ink' : 'text-mute')}>
            {status.text || (dirty ? 'Ada perubahan yang belum disimpan.' : 'Semua perubahan sudah tersimpan.')}
          </p>
          <motion.button
            type="button"
            id="save"
            onClick={save}
            disabled={saving}
            whileTap={{ scale: 0.97 }}
            className="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-gradient-to-br from-brand to-brand-strong px-5 py-2.5 text-sm font-semibold text-paper shadow-glow disabled:opacity-60"
          >
            {saving ? <LoaderCircle className="size-4 animate-spin" /> : <Save className="size-4" />}
            Simpan perubahan
          </motion.button>
        </motion.div>
      </div>
    </div>
  );
}

import { useEffect, useState } from 'react';
import { motion, animate } from 'motion/react';
import { Area, AreaChart, CartesianGrid, Cell, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { Bot, CircleCheck, Clock, FileText, MessagesSquare, SlidersHorizontal, TriangleAlert, Users, ArrowUpRight, FileSpreadsheet, Timer } from 'lucide-react';

const ease = [0.22, 1, 0.36, 1];
const fade = (i = 0) => ({ initial: { opacity: 0, y: 16 }, animate: { opacity: 1, y: 0 }, transition: { duration: 0.5, ease, delay: 0.05 * i } });

function CountUp({ value, suffix = '' }) {
  const [v, setV] = useState(0);
  useEffect(() => {
    const c = animate(0, value, { duration: 1.1, ease, onUpdate: (x) => setV(Math.round(x)) });
    return () => c.stop();
  }, [value]);
  return <>{v.toLocaleString('id-ID')}{suffix}</>;
}

function StatCard({ i, icon: Ico, label, value, suffix, hint, tone }) {
  const tones = {
    brand: 'bg-tint text-brand',
    gold: 'bg-gold-soft text-gold',
    info: 'bg-info-bg text-info-ink',
    rose: 'bg-danger-bg text-danger-ink',
  };
  return (
    <motion.div {...fade(i)} whileHover={{ y: -3 }} className="group relative overflow-hidden rounded-3xl border border-line bg-card p-5 shadow-card transition-shadow hover:shadow-lift">
      <div className="pointer-events-none absolute -top-12 -right-12 size-32 rounded-full bg-brand/0 blur-2xl transition-colors duration-500 group-hover:bg-brand/10" />
      <div className="flex items-start justify-between">
        <span className={'grid size-11 place-items-center rounded-2xl ' + tones[tone]}><Ico className="size-5" /></span>
      </div>
      <div className="mt-4 text-3xl font-extrabold tracking-tight tabular-nums"><CountUp value={value} suffix={suffix} /></div>
      <div className="mt-1 text-sm font-semibold">{label}</div>
      <div className="mt-0.5 text-xs text-mute">{hint}</div>
    </motion.div>
  );
}

function ChartTip({ active, payload, label }) {
  if (!active || !payload?.length) return null;
  return (
    <div className="rounded-xl border border-line bg-card px-3 py-2 text-xs shadow-lift">
      <div className="mb-1 font-semibold">{label}</div>
      {payload.map((p) => (
        <div key={p.dataKey} className="flex items-center gap-2 text-mute">
          <span className="size-2 rounded-full" style={{ background: p.color }} />
          {p.dataKey === 'questions' ? 'Pertanyaan' : 'Jawaban'}: <b className="text-ink">{p.value}</b>
        </div>
      ))}
    </div>
  );
}

const STATUS = {
  indexed: { label: 'Terindeks', color: 'var(--brand)', cls: 'bg-success-bg text-success-ink' },
  pending: { label: 'Menunggu', color: 'var(--gold)', cls: 'bg-warning-bg text-warning-ink' },
  failed: { label: 'Gagal', color: 'var(--danger)', cls: 'bg-danger-bg text-danger-ink' },
};

const fmtDate = (iso) => new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
const fmtSize = (b) => (b >= 1048576 ? (b / 1048576).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' MB' : (b / 1024).toLocaleString('id-ID', { maximumFractionDigits: 0 }) + ' KB');

export default function Overview({ stats }) {
  const d = stats.documents;
  const pie = ['indexed', 'pending', 'failed'].map((k) => ({ key: k, name: STATUS[k].label, value: d[k] })).filter((x) => x.value > 0);
  const avgSec = Math.round((stats.avg_response_ms || 0) / 100) / 10;

  return (
    <div className="space-y-6">
      {/* Banner status */}
      <motion.div {...fade(0)} className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-night to-night-2 p-6 text-white shadow-lift sm:p-7">
        <div className="pointer-events-none absolute -top-20 -right-10 size-72 rounded-full bg-[color-mix(in_oklab,var(--brand)_40%,transparent)] blur-3xl" />
        <div className="pointer-events-none absolute -bottom-24 left-1/3 size-60 rounded-full bg-[color-mix(in_oklab,var(--gold)_30%,transparent)] blur-3xl" />
        <div className="relative flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <div className={'inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold ring-1 ' + (stats.ai_online ? 'bg-white/10 ring-white/20' : 'bg-red-500/20 ring-red-300/30')}>
              <span className="relative flex size-2">
                {stats.ai_online ? <span className="absolute inline-flex size-full animate-ping rounded-full bg-emerald-300 opacity-60" /> : null}
                <span className={'relative inline-flex size-2 rounded-full ' + (stats.ai_online ? 'bg-emerald-300' : 'bg-red-300')} />
              </span>
              {stats.ai_online ? 'Layanan AI berjalan normal' : 'Layanan AI (Python) tidak terhubung'}
            </div>
            <h2 className="mt-3 font-serif text-2xl font-bold sm:text-3xl">
              {stats.questions_week.toLocaleString('id-ID')} pertanyaan minggu ini
            </h2>
            <p className="mt-1 max-w-lg text-sm text-white/70">
              Chatbot memakai {d.indexed.toLocaleString('id-ID')} dokumen terindeks sebagai rujukan. Rata-rata waktu jawab {avgSec.toLocaleString('id-ID')} detik.
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <a href={stats.routes.ai_chat} className="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-[#062a1a] transition hover:bg-white/90">
              <Bot className="size-4" />Uji chatbot
            </a>
            <a href={stats.routes.ai_config} className="inline-flex items-center gap-2 rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold ring-1 ring-white/20 transition hover:bg-white/15">
              <SlidersHorizontal className="size-4" />Konfigurasi AI
            </a>
          </div>
        </div>
      </motion.div>

      {/* Kartu angka */}
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard i={1} icon={FileText} tone="brand" label="Dokumen terindeks" value={d.indexed} hint={`${d.total} dokumen diunggah`} />
        <StatCard i={2} icon={MessagesSquare} tone="gold" label="Total pertanyaan" value={stats.questions} hint={`${stats.answers.toLocaleString('id-ID')} sudah dijawab`} />
        <StatCard i={3} icon={Users} tone="info" label="Pengguna terdaftar" value={stats.users} hint={`${stats.sessions.toLocaleString('id-ID')} percakapan tersimpan`} />
        <StatCard i={4} icon={Timer} tone="rose" label="Rata-rata waktu jawab" value={avgSec} suffix=" dtk" hint="Dari server AI lokal" />
      </div>

      <div className="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        {/* Grafik aktivitas */}
        <motion.section {...fade(5)} className="rounded-3xl border border-line bg-card p-5 shadow-card sm:p-6">
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h2 className="font-serif text-lg font-bold">Aktivitas 14 hari terakhir</h2>
              <p className="text-xs text-mute">Jumlah pertanyaan dan jawaban per hari (WIB).</p>
            </div>
            <div className="flex items-center gap-4 text-xs font-medium text-mute">
              <span className="flex items-center gap-1.5"><span className="size-2.5 rounded-full bg-brand" />Pertanyaan</span>
              <span className="flex items-center gap-1.5"><span className="size-2.5 rounded-full bg-gold" />Jawaban</span>
            </div>
          </div>
          <div className="mt-5 h-72">
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={stats.daily} margin={{ top: 5, right: 5, left: -20, bottom: 0 }}>
                <defs>
                  <linearGradient id="gQ" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor="var(--brand)" stopOpacity={0.35} />
                    <stop offset="100%" stopColor="var(--brand)" stopOpacity={0} />
                  </linearGradient>
                  <linearGradient id="gA" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor="var(--gold)" stopOpacity={0.25} />
                    <stop offset="100%" stopColor="var(--gold)" stopOpacity={0} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="4 4" stroke="var(--line)" vertical={false} />
                <XAxis dataKey="label" tick={{ fill: 'var(--mute)', fontSize: 11 }} axisLine={false} tickLine={false} interval="preserveStartEnd" />
                <YAxis allowDecimals={false} tick={{ fill: 'var(--mute)', fontSize: 11 }} axisLine={false} tickLine={false} />
                <Tooltip content={<ChartTip />} cursor={{ stroke: 'var(--line)' }} />
                <Area type="monotone" dataKey="answers" stroke="var(--gold)" strokeWidth={2} fill="url(#gA)" animationDuration={900} />
                <Area type="monotone" dataKey="questions" stroke="var(--brand)" strokeWidth={2.5} fill="url(#gQ)" animationDuration={900} />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </motion.section>

        {/* Status dokumen */}
        <motion.section {...fade(6)} className="rounded-3xl border border-line bg-card p-5 shadow-card sm:p-6">
          <h2 className="font-serif text-lg font-bold">Status dokumen</h2>
          <p className="text-xs text-mute">Hanya dokumen terindeks yang dipakai chatbot.</p>
          {d.total ? (
            <div className="relative mx-auto mt-4 h-48 max-w-[14rem]">
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie data={pie} dataKey="value" nameKey="name" innerRadius="68%" outerRadius="100%" paddingAngle={3} stroke="none" animationDuration={900}>
                    {pie.map((p) => <Cell key={p.key} fill={STATUS[p.key].color} />)}
                  </Pie>
                </PieChart>
              </ResponsiveContainer>
              <div className="pointer-events-none absolute inset-0 grid place-items-center text-center">
                <div>
                  <div className="text-3xl font-extrabold"><CountUp value={d.total} /></div>
                  <div className="text-xs text-mute">dokumen</div>
                </div>
              </div>
            </div>
          ) : (
            <div className="mt-6 grid place-items-center rounded-2xl border border-dashed border-line py-10 text-sm text-mute">Belum ada dokumen.</div>
          )}
          <div className="mt-5 space-y-2">
            {['indexed', 'pending', 'failed'].map((k) => (
              <div key={k} className="flex items-center justify-between rounded-xl bg-paper px-3 py-2 text-sm">
                <span className="flex items-center gap-2"><span className="size-2.5 rounded-full" style={{ background: STATUS[k].color }} />{STATUS[k].label}</span>
                <span className="font-semibold tabular-nums">{d[k]}</span>
              </div>
            ))}
          </div>
        </motion.section>
      </div>

      {/* Dokumen terbaru */}
      <motion.section {...fade(7)} className="overflow-hidden rounded-3xl border border-line bg-card shadow-card">
        <div className="flex items-center justify-between gap-3 border-b border-line px-5 py-4 sm:px-6">
          <div>
            <h2 className="font-serif text-lg font-bold">Dokumen terbaru</h2>
            <p className="text-xs text-mute">Lima dokumen yang terakhir diunggah atau diperbarui.</p>
          </div>
          <a href={stats.routes.documents} className="inline-flex items-center gap-1 text-sm font-semibold text-brand hover:underline">
            Kelola <ArrowUpRight className="size-4" />
          </a>
        </div>
        {stats.recent_documents.length ? (
          <ul className="divide-y divide-line">
            {stats.recent_documents.map((doc, i) => {
              const sheet = doc.name.toLowerCase().endsWith('.xlsx');
              const st = STATUS[doc.status] || STATUS.pending;
              const StIcon = doc.status === 'indexed' ? CircleCheck : doc.status === 'failed' ? TriangleAlert : Clock;
              return (
                <motion.li
                  key={doc.name + i}
                  initial={{ opacity: 0, x: -8 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ delay: 0.4 + i * 0.05 }}
                  className="flex items-center gap-3 px-5 py-3.5 sm:px-6"
                >
                  <span className={'grid size-10 shrink-0 place-items-center rounded-xl ' + (sheet ? 'bg-success-bg text-success-ink' : 'bg-danger-bg text-danger-ink')}>
                    {sheet ? <FileSpreadsheet className="size-5" /> : <FileText className="size-5" />}
                  </span>
                  <div className="min-w-0 flex-1">
                    <div className="truncate text-sm font-medium">{doc.name}</div>
                    <div className="text-xs text-mute">{fmtSize(doc.size)} · {fmtDate(doc.updated_at)}</div>
                  </div>
                  <span className={'inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold ' + st.cls}>
                    <StIcon className="size-3.5" />{st.label}
                  </span>
                </motion.li>
              );
            })}
          </ul>
        ) : (
          <div className="px-6 py-10 text-center text-sm text-mute">Belum ada dokumen yang diunggah.</div>
        )}
      </motion.section>
    </div>
  );
}

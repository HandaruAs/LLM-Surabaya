import { motion } from 'motion/react';
import Icon from './Icon';
import { greet, TOPICS } from '../lib';

// Layar sambutan: tanggal, sapaan bergradasi, dan kartu contoh pertanyaan.
const container = { hidden: {}, show: { transition: { staggerChildren: 0.07, delayChildren: 0.05 } } };
const item = {
  hidden: { opacity: 0, y: 16 },
  show: { opacity: 1, y: 0, transition: { duration: 0.5, ease: [0.22, 1, 0.36, 1] } },
};

const TONE = {
  brand: 'bg-tint text-brand',
  gold: 'bg-gold-soft text-gold',
  info: 'bg-info-bg text-info-ink',
  rose: 'bg-danger-bg text-danger-ink',
};


export default function Welcome({ userName, onAsk }) {
  const date = new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

  return (
    <motion.div variants={container} initial="hidden" animate="show" className="mx-auto flex w-full max-w-3xl flex-col items-center pt-6 text-center md:pt-14">
      <motion.div variants={item} className="inline-flex items-center gap-2 rounded-full border border-line bg-card/80 px-3 py-1 text-xs font-medium text-mute shadow-card">
        <span className="relative flex size-2">
          <span className="absolute inline-flex size-full animate-ping rounded-full bg-gold opacity-60" />
          <span className="relative inline-flex size-2 rounded-full bg-gold" />
        </span>
        {date}
      </motion.div>

      <motion.h2 variants={item} className="mt-5 font-serif text-3xl leading-tight font-bold tracking-tight text-balance md:text-[2.6rem]">
        {greet(userName)}.<br />
        <span className="text-gradient">Apa yang ingin Anda ketahui?</span>
      </motion.h2>
      <motion.p variants={item} className="mt-4 max-w-[56ch] text-[15px] leading-7 text-mute">
        Tanyakan seputar pajak daerah, anggaran, dan retribusi Kota Surabaya. Pilih contoh di bawah atau tulis pertanyaan sendiri.
      </motion.p>

      {TOPICS.length ? (
        <motion.div variants={container} className="mt-9 grid w-full gap-3 text-left sm:grid-cols-2">
          {TOPICS.map((tp) => (
            <motion.button
              key={tp.q}
              type="button"
              variants={item}
              whileHover={{ y: -4 }}
              whileTap={{ scale: 0.98 }}
              onClick={() => onAsk(tp.q)}
              className="group relative flex cursor-pointer items-start gap-3.5 overflow-hidden text-left rounded-2xl border border-line bg-card p-4 shadow-card transition-[border-color,box-shadow] hover:border-brand/50 hover:shadow-lift"
            >
              <span className="pointer-events-none absolute -top-10 -right-10 size-28 rounded-full bg-brand/0 blur-2xl transition-colors duration-500 group-hover:bg-brand/15" />
              <span className={'grid size-11 shrink-0 place-items-center rounded-xl ' + (TONE[tp.tone] || TONE.brand)}>
                <Icon name={tp.icon} className="size-5" />
              </span>
              <span className="relative min-w-0 flex-1">
                <span className="flex items-center justify-between gap-2">
                  <span className="font-semibold">{tp.title}</span>
                  <Icon name="arrow" className="size-4 text-mute transition group-hover:translate-x-1 group-hover:text-brand" />
                </span>
                <span className="mt-1 block text-sm leading-5 text-mute">{tp.q}</span>
              </span>
            </motion.button>
          ))}
        </motion.div>
      ) : null}

    </motion.div>
  );
}

import { motion } from 'motion/react';
import Icon from './Icon';
import { greet, TOPICS } from '../lib';

// Layar sambutan (nomor 4): elemen muncul berurutan, kartu contoh pertanyaan terangkat saat di-hover.
const container = { hidden: {}, show: { transition: { staggerChildren: 0.08, delayChildren: 0.05 } } };
const item = {
  hidden: { opacity: 0, y: 14 },
  show: { opacity: 1, y: 0, transition: { duration: 0.45, ease: [0.22, 1, 0.36, 1] } },
};

export default function Welcome({ userName, onAsk }) {
  const date = new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

  return (
    <motion.div variants={container} initial="hidden" animate="show" className="mx-auto w-full max-w-3xl">
      <motion.div variants={item} className="inline-flex items-center gap-2 rounded-full border border-line bg-card px-3 py-1 text-xs font-medium text-mute">
        <span className="size-1.5 rounded-full bg-gold" />{date}
      </motion.div>
      <motion.h2 variants={item} className="mt-4 font-serif text-3xl font-semibold leading-tight tracking-tight md:text-4xl">
        {greet(userName)}. Apa yang ingin Anda ketahui?
      </motion.h2>
      <motion.div
        variants={{ hidden: { scaleX: 0 }, show: { scaleX: 1, transition: { duration: 0.5, ease: [0.22, 1, 0.36, 1] } } }}
        style={{ originX: 0 }}
        className="my-5 h-[3px] w-14 rounded-full bg-gold"
      />
      <motion.p variants={item} className="max-w-[60ch] text-mute">
        Tanyakan seputar pajak daerah, anggaran, dan retribusi Kota Surabaya. Jawaban dilengkapi rujukan agar mudah ditelusuri.
      </motion.p>

      {TOPICS.length ? (
        <motion.div variants={container} className="mt-8 grid gap-3 sm:grid-cols-2">
          {TOPICS.map((tp) => (
            <motion.button
              key={tp.q}
              type="button"
              variants={item}
              whileHover={{ y: -3 }}
              whileTap={{ scale: 0.98 }}
              onClick={() => onAsk(tp.q)}
              className="group flex cursor-pointer flex-col gap-3 rounded-2xl border border-line bg-card p-4 text-left shadow-card transition-[border-color,box-shadow] hover:border-brand hover:shadow-lift"
            >
              <div className="flex items-center justify-between">
                <span className="grid size-10 place-items-center rounded-xl bg-tint text-brand"><Icon name={tp.icon} className="size-5" /></span>
                <Icon name="arrow" className="size-4 text-mute transition group-hover:translate-x-1 group-hover:text-brand" />
              </div>
              <div>
                <div className="font-serif text-[17px] font-semibold leading-snug">{tp.title}</div>
                <div className="mt-1 text-sm leading-5 text-mute">{tp.q}</div>
              </div>
            </motion.button>
          ))}
        </motion.div>
      ) : null}
    </motion.div>
  );
}

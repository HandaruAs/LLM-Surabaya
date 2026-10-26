import { useEffect, useState } from 'react';
import { AnimatePresence, motion } from 'motion/react';
import logo from '../../../images/lambang-surabaya.webp';

// Animasi pembuka setelah login/daftar (±1,8 detik): lambang Surabaya (hiu & buaya) muncul,
// lalu lapisan ini memudar dan halaman chat yang sudah siap di belakangnya terlihat.
// Diputar sekali saja (server mengirim penanda 'welcome_intro' satu kali). Klik / Esc untuk melewati.
const HOLD_MS = 1300; // lama logo tampil sebelum mulai memudar (memudar ±0,5 detik)

export default function Intro({ name }) {
  const [show, setShow] = useState(true);

  useEffect(() => {
    const t = setTimeout(() => setShow(false), HOLD_MS);
    const onKey = (e) => { if (e.key === 'Escape') setShow(false); };
    window.addEventListener('keydown', onKey);
    return () => { clearTimeout(t); window.removeEventListener('keydown', onKey); };
  }, []);

  const fullName = (name || '').replace(/\s+/g, ' ').trim();

  return (
    <AnimatePresence>
      {show ? (
        <motion.div
          key="intro"
          onClick={() => setShow(false)}
          initial={{ opacity: 1 }}
          exit={{ opacity: 0, transition: { duration: 0.5, ease: 'easeInOut' } }}
          className="fixed inset-0 z-[60] grid cursor-pointer place-items-center bg-paper"
          aria-hidden="true"
        >
          <div className="flex flex-col items-center">
            <div className="relative grid place-items-center">
              {/* Cincin emas yang melebar di belakang lambang */}
              <motion.span
                className="absolute size-44 rounded-full border-2 border-gold md:size-52"
                initial={{ scale: 0.6, opacity: 0 }}
                animate={{ scale: [0.6, 1.25], opacity: [0, 0.7, 0] }}
                transition={{ duration: 1.1, ease: 'easeOut', delay: 0.15 }}
              />
              <motion.span
                className="absolute size-40 rounded-full bg-tint md:size-48"
                initial={{ scale: 0 }}
                animate={{ scale: 1 }}
                transition={{ type: 'spring', stiffness: 220, damping: 20 }}
              />
              <motion.img
                src={logo}
                alt=""
                width="360"
                height="463"
                className="relative h-auto w-28 drop-shadow-lg md:w-32"
                initial={{ scale: 0.5, opacity: 0, rotate: -10, y: 12 }}
                animate={{ scale: 1, opacity: 1, rotate: 0, y: 0 }}
                exit={{ scale: 1.15, opacity: 0 }}
                transition={{ type: 'spring', stiffness: 260, damping: 18, delay: 0.05 }}
              />
            </div>

            <motion.div
              className="mt-8 text-center"
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.4, ease: [0.22, 1, 0.36, 1], delay: 0.35 }}
            >
              <div className="max-w-[90vw] break-words px-4 font-serif text-2xl font-semibold">{fullName ? `Selamat datang, ${fullName}` : 'Selamat datang'}</div>
              <div className="mt-1 text-sm text-mute">LLM Surabaya · Informasi keuangan daerah</div>
            </motion.div>
          </div>
        </motion.div>
      ) : null}
    </AnimatePresence>
  );
}

import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { MotionConfig } from 'motion/react';
import App from './App';
import Intro from './components/Intro';
import { IconsContext } from './components/Icon';
import { ToastProvider } from './components/Toaster';

// Halaman chat versi React. Data awal (user, route, ikon, FAQ) dikirim dari chat.blade.php lewat data-config.
const root = document.getElementById('chat-root');

if (root) {
  const cfg = JSON.parse(root.dataset.config || '{}');
  createRoot(root).render(
    <StrictMode>
      {/* reducedMotion="user": animasi otomatis dimatikan kalau perangkat memakai pengaturan "kurangi gerakan". */}
      <MotionConfig reducedMotion="user">
        <IconsContext.Provider value={cfg.icons || {}}>
          <ToastProvider>
            <App cfg={cfg} />
            {cfg.intro ? <Intro name={cfg.user?.name} /> : null}
          </ToastProvider>
        </IconsContext.Provider>
      </MotionConfig>
    </StrictMode>,
  );
}

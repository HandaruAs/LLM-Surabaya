import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { MotionConfig } from 'motion/react';
import * as Tooltip from '@radix-ui/react-tooltip';
import App from './App';
import Intro from './components/Intro';
import { ToastProvider } from './components/Toaster';

// Halaman chat versi React. Data awal (user, route, FAQ) dikirim dari chat.blade.php lewat data-config.
const root = document.getElementById('chat-root');

if (root) {
  const cfg = JSON.parse(root.dataset.config || '{}');
  createRoot(root).render(
    <StrictMode>
      {/* reducedMotion="user": animasi otomatis dimatikan kalau perangkat memakai pengaturan "kurangi gerakan". */}
      <MotionConfig reducedMotion="user">
        <Tooltip.Provider delayDuration={300}>
          <ToastProvider>
            <App cfg={cfg} />
            {cfg.intro ? <Intro name={cfg.user?.name} /> : null}
          </ToastProvider>
        </Tooltip.Provider>
      </MotionConfig>
    </StrictMode>,
  );
}

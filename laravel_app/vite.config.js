import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // resources/js/chat/main.jsx = halaman chat versi React (dipakai di chat.blade.php).
            // resources/js/auth.js = animasi halaman Masuk/Daftar (dipakai di auth/layout.blade.php).
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/chat/main.jsx', 'resources/js/auth.js'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
});

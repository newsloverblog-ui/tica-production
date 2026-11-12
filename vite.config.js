import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        react(),
        tailwindcss(),
        laravel({
            input: ['resources/css/tica-front.css', 'resources/js/tica-app.tsx'],
            refresh: true,
        }),
    ],
});

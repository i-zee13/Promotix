import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: process.env.VITE_DOCKER
        ? { host: '0.0.0.0', hmr: { host: 'localhost', port: 8000 } }
        : {},
});

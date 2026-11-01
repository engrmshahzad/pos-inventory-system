import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    build: {
        chunkSizeWarningLimit: 700,
        rollupOptions: {
            output: {
                // Separate, long-cached vendor chunks.
                manualChunks: {
                    charts: ['apexcharts'],
                    vendor: ['alpinejs', 'tom-select', 'sweetalert2', 'axios'],
                },
            },
        },
    },
});

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/print.css',
                'resources/js/theme.js',
                'resources/js/app.js',
                'resources/js/print.js',
            ],
            refresh: true,
        }),
    ],
});

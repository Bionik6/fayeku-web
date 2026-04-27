import {
    defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            // Keep CSS/JS HMR, but avoid unexpected full-page reloads while the
            // Laravel app is running in local development.
            refresh: false,
        }),
        tailwindcss(),
    ],
    server: {
        host: '127.0.0.1',
        hmr: {
            host: '127.0.0.1',
        },
        cors: true,
        watch: {
            ignored: [
                '**/.junie/**',
                '**/storage/framework/views/**',
                '**/bootstrap/cache/**',
                '**/storage/logs/**',
            ],
        },
    },
});

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/contact.css',
                'resources/css/home.css',
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/public-awards.js',
                'resources/css/dashboard.css',
                'resources/css/ui.css',
                'resources/css/portal-shell.css',
                'resources/js/portal.js',
                'resources/css/project-wizard.css',
                'resources/js/project-wizard.js',
                'resources/js/dashboard.js',
                'resources/css/bid-management.css',
                'resources/css/admin-projects.css',
                'resources/js/bid-management.js',
                'resources/js/bid-direct-upload.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});

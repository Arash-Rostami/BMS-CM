import {defineConfig} from 'vite';
import laravel from 'laravel-vite-plugin';
import {viteStaticCopy} from 'vite-plugin-static-copy';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/fi-custom.css',
                'resources/css/layout/fonts.css',
                'resources/css/landing-page.css',
                'resources/js/app.js',
                'resources/js/filament/nav-dock.js',
                'resources/js/filament/topbar-autohide.js',
            ],
            refresh: true,
        }),
        viteStaticCopy({
            targets: [
                { src: 'resources/img/*', dest: '../img/' },
                { src: 'resources/audio/*', dest: '../audio/' },
                { src: 'resources/video/*', dest: '../video/' },
                { src: 'resources/fonts/*', dest: '../fonts/' }
            ]
        })
    ]
});

import {defineConfig} from 'vite';
import laravel from 'laravel-vite-plugin';
import {viteStaticCopy} from 'vite-plugin-static-copy';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/layout/fonts.css',
                'resources/css/landing-page.css',
                'resources/js/app.js',
                'resources/js/landing-page.js',
                'resources/js/3d.min.js',
            ],
            refresh: true,
        }),
        viteStaticCopy({
            targets: [{
                src: 'resources/img/*',
                dest: '../img/'
            }]
        })
    ]
});

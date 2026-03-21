import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { readFileSync } from 'fs';

const pkg = JSON.parse(readFileSync('./package.json', 'utf-8'));

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js'],
            refresh: true,
        }),
        vue(),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
            '@css': '/resources/css',
            '@stores': '/resources/js/stores',
        },
    },
    assetsInclude: ['**/*.JPG', '**/*.jpg', '**/*.jpeg', '**/*.png', '**/*.gif', '**/*.svg'],
    define: {
        __APP_VERSION__: JSON.stringify(pkg.version),
        __VUE_I18N_LEGACY_API__: false,
        __VUE_I18N_FULL_INSTALL__: true,
        __INTLIFY_PROD_DEVTOOLS__: false,
    },
});

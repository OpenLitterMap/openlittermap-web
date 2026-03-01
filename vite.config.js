import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';

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
        __VUE_I18N_LEGACY_API__: false,
        __VUE_I18N_FULL_INSTALL__: true,
        __INTLIFY_PROD_DEVTOOLS__: false,
    },
});
